<?php

namespace App\Modules\Financial\Application;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Sale;
use App\Tenancy\CurrentShop;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\Access\AuthorizationException;

class ReconciliationService
{
    public function __construct(
        private readonly CurrentShop $currentShop,
        private readonly BalanceProjectionService $projections,
    ) {}

    /**
     * Read-only reconciliation. It never creates an account, changes a snapshot,
     * updates a Sale status or posts a financial entry.
     *
     * @return array{shop_id:int,customer_id:int,consistent:bool,discrepancies:list<array<string,mixed>>}
     */
    public function reconcileCustomer(Customer $customer): array
    {
        $shopId = (int) $this->currentShop->require()->getKey();

        if ((int) $customer->shop_id !== $shopId) {
            throw new AuthorizationException('The Customer does not belong to the current Shop.');
        }

        $discrepancies = [];
        $expected = $this->projections->ledgerBalances($customer);
        $account = CustomerAccount::query()
            ->where('shop_id', $shopId)
            ->where('customer_id', $customer->getKey())
            ->first();

        if ($account === null) {
            $discrepancies[] = [
                'type' => 'missing_account_projection',
                'expected' => $expected,
            ];
        } else {
            foreach ($expected as $field => $value) {
                $actual = (string) $account->{$field};

                if (BigDecimal::of($actual)->compareTo($value) !== 0) {
                    $discrepancies[] = [
                        'type' => 'account_projection_mismatch',
                        'field' => $field,
                        'expected' => $value,
                        'actual' => $actual,
                    ];
                }
            }
        }

        $this->compareInterestCheckpoint($customer, $account, $discrepancies);
        $this->compareSaleStatuses($customer, $discrepancies);
        $this->compareSnapshots($customer, $discrepancies);
        $this->compareSourcePostings($customer, $discrepancies);

        return [
            'shop_id' => $shopId,
            'customer_id' => (int) $customer->getKey(),
            'consistent' => $discrepancies === [],
            'discrepancies' => $discrepancies,
        ];
    }

    /**
     * Explicit repair path. The projection is rebuilt from the ledger under the
     * normal customer/account lock, then the read-only reconciliation is rerun.
     *
     * @return array{shop_id:int,customer_id:int,consistent:bool,discrepancies:list<array<string,mixed>>}
     */
    public function rebuildAndReconcile(Customer $customer): array
    {
        $this->projections->rebuildForCustomer($customer);

        return $this->reconcileCustomer($customer);
    }

    /**
     * @param  list<array<string,mixed>>  $discrepancies
     */
    private function compareInterestCheckpoint(Customer $customer, ?CustomerAccount $account, array &$discrepancies): void
    {
        $expected = DB::table('interest_calculation_runs')
            ->where('shop_id', $customer->shop_id)
            ->where('customer_id', $customer->getKey())
            ->where('status', 'COMPLETED')
            ->max('cutoff_date_exclusive');
        $actual = $account?->last_interest_cutoff_date?->format('Y-m-d');

        if ($actual !== $expected) {
            $discrepancies[] = [
                'type' => 'interest_checkpoint_mismatch',
                'expected' => $expected,
                'actual' => $actual,
            ];
        }
    }

    /**
     * @param  list<array<string,mixed>>  $discrepancies
     */
    private function compareSaleStatuses(Customer $customer, array &$discrepancies): void
    {
        $expected = $this->projections->salePaymentStatuses($customer);

        foreach (Sale::query()
            ->where('shop_id', $customer->shop_id)
            ->where('customer_id', $customer->getKey())
            ->whereNotNull('posted_at')
            ->get(['id', 'payment_status']) as $sale) {
            $expectedStatus = $expected[(int) $sale->getKey()] ?? null;

            if ($expectedStatus !== null && $sale->payment_status !== $expectedStatus) {
                $discrepancies[] = [
                    'type' => 'sale_payment_status_mismatch',
                    'sale_id' => (int) $sale->getKey(),
                    'expected' => $expectedStatus,
                    'actual' => $sale->payment_status,
                ];
            }
        }
    }

    /**
     * @param  list<array<string,mixed>>  $discrepancies
     */
    private function compareSnapshots(Customer $customer, array &$discrepancies): void
    {
        $snapshotDates = DB::table('account_balance_snapshots')
            ->where('shop_id', $customer->shop_id)
            ->where('customer_id', $customer->getKey())
            ->pluck('as_of_date')
            ->map(fn ($date): string => (string) $date)
            ->all();

        $ledgerDates = LedgerEntry::query()
            ->where('shop_id', $customer->shop_id)
            ->where('customer_id', $customer->getKey())
            ->distinct()
            ->pluck('effective_date')
            ->map(fn ($date): string => (string) $date)
            ->all();

        $dates = array_values(array_unique([...$snapshotDates, ...$ledgerDates]));
        sort($dates, SORT_STRING);

        foreach ($dates as $date) {
            $snapshot = DB::table('account_balance_snapshots')
                ->where('shop_id', $customer->shop_id)
                ->where('customer_id', $customer->getKey())
                ->where('as_of_date', $date)
                ->first();
            $expected = $this->projections->ledgerBalances($customer, $date);

            if ($snapshot === null) {
                $discrepancies[] = [
                    'type' => 'missing_balance_snapshot',
                    'as_of_date' => $date,
                    'expected' => $expected,
                ];

                continue;
            }

            foreach ($expected as $field => $value) {
                if (BigDecimal::of((string) $snapshot->{$field})->compareTo($value) !== 0) {
                    $discrepancies[] = [
                        'type' => 'balance_snapshot_mismatch',
                        'as_of_date' => $date,
                        'field' => $field,
                        'expected' => $value,
                        'actual' => (string) $snapshot->{$field},
                    ];
                }
            }
        }
    }

    /**
     * Verify that each posted Sale and Payment has its complete ledger source.
     *
     * @param  list<array<string,mixed>>  $discrepancies
     */
    private function compareSourcePostings(Customer $customer, array &$discrepancies): void
    {
        foreach (Sale::query()
            ->where('shop_id', $customer->shop_id)
            ->where('customer_id', $customer->getKey())
            ->whereNotNull('posted_at')
            ->get(['id', 'amount']) as $sale) {
            $entries = LedgerEntry::query()
                ->where('shop_id', $customer->shop_id)
                ->where('customer_id', $customer->getKey())
                ->where('sale_id', $sale->getKey())
                ->where('entry_kind', 'SALE')
                ->where('component', 'PRINCIPAL');
            $net = BigDecimal::of((string) ((clone $entries)->sum('debit_amount') ?: '0.00'))
                ->minus((string) ((clone $entries)->sum('credit_amount') ?: '0.00'))
                ->toScale(2);

            if ($net->compareTo((string) $sale->amount) !== 0) {
                $discrepancies[] = [
                    'type' => 'sale_ledger_source_mismatch',
                    'sale_id' => (int) $sale->getKey(),
                    'expected' => (string) $sale->amount,
                    'actual' => (string) $net,
                ];
            }
        }

        foreach (Payment::query()
            ->where('shop_id', $customer->shop_id)
            ->where('customer_id', $customer->getKey())
            ->whereNotNull('posted_at')
            ->get(['id', 'amount', 'payment_type']) as $payment) {
            $entries = LedgerEntry::query()
                ->where('shop_id', $customer->shop_id)
                ->where('customer_id', $customer->getKey())
                ->where('payment_id', $payment->getKey());
            $net = BigDecimal::of((string) ((clone $entries)->sum('credit_amount') ?: '0.00'))
                ->minus((string) ((clone $entries)->sum('debit_amount') ?: '0.00'))
                ->toScale(2);
            $expected = $payment->payment_type === Payment::TYPE_REVERSAL
                ? BigDecimal::of((string) $payment->amount)->negated()
                : BigDecimal::of((string) $payment->amount);

            if ($net->compareTo($expected) !== 0) {
                $discrepancies[] = [
                    'type' => 'payment_ledger_source_mismatch',
                    'payment_id' => (int) $payment->getKey(),
                    'expected' => (string) $expected,
                    'actual' => (string) $net,
                ];
            }
        }
    }
}
