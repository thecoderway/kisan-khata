<?php

namespace App\Modules\Financial\Application;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Sale;
use App\Tenancy\CurrentShop;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ReconciliationService
{
    public function __construct(
        private readonly CurrentShop $currentShop,
        private readonly BalanceProjectionService $projections,
        private readonly CustomerAccountLockService $accountLock,
    ) {}

    /**
     * Read-only reconciliation. It never creates an account, changes a snapshot,
     * updates a Sale status or posts a financial entry.
     *
     * @return array{shop_id:int,customer_id:int,consistent:bool,discrepancies:list<array<string,mixed>>}
     */
    public function reconcileCustomer(Customer $customer): array
    {
        return DB::transaction(fn (): array => $this->reconcileCustomerWithinTransaction($customer));
    }

    /**
     * Explicit repair path. Acquire customer/account locks before the first
     * consistent read so a REPEATABLE READ snapshot cannot predate a posting
     * that commits while this repair waits for its lock. The callback runs
     * inside the same transaction, after a successful rebuild and before commit.
     *
     * @param  null|callable(array<string,mixed>, array<string,mixed>):void  $afterRepair
     * @return array{report:array{shop_id:int,customer_id:int,consistent:bool,discrepancies:list<array<string,mixed>>},repaired:bool}
     */
    public function repairAndReconcile(Customer $customer, ?callable $afterRepair = null): array
    {
        return DB::transaction(function () use ($customer, $afterRepair): array {
            $account = $this->accountLock->lockForCustomer($customer);
            $before = $this->reconcileCustomerWithinTransaction($customer);

            if ($before['consistent']) {
                return ['report' => $before, 'repaired' => false];
            }

            $this->projections->rebuildLocked($account, $customer);
            $after = $this->reconcileCustomerWithinTransaction($customer);

            if (! $after['consistent']) {
                throw new \RuntimeException('Projection repair did not produce a consistent customer account.');
            }

            if ($afterRepair !== null) {
                $afterRepair($before, $after);
            }

            return ['report' => $after, 'repaired' => true];
        }, 3);
    }

    /**
     * Backwards-compatible repair API for internal callers that do not need
     * to record an audit event in the repair transaction.
     *
     * @return array{shop_id:int,customer_id:int,consistent:bool,discrepancies:list<array<string,mixed>>}
     */
    public function rebuildAndReconcile(Customer $customer): array
    {
        return $this->repairAndReconcile($customer)['report'];
    }

    /**
     * Reconcile using the caller's current transaction and snapshot.
     * Repair callers must acquire the customer/account lock before invoking it.
     *
     * @return array{shop_id:int,customer_id:int,consistent:bool,discrepancies:list<array<string,mixed>>}
     */
    private function reconcileCustomerWithinTransaction(Customer $customer): array
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
            $discrepancies[] = ['type' => 'missing_account_projection', 'expected' => $expected];
        } else {
            foreach ($expected as $field => $value) {
                $actual = (string) $account->{$field};
                if (BigDecimal::of($actual)->compareTo($value) !== 0) {
                    $discrepancies[] = ['type' => 'account_projection_mismatch', 'field' => $field, 'expected' => $value, 'actual' => $actual];
                }
            }
        }

        $this->compareLastFinancialEvent($customer, $account, $discrepancies);
        $this->compareInterestCheckpoint($customer, $account, $discrepancies);
        $this->compareSaleStatuses($customer, $discrepancies);
        $this->compareSnapshots($customer, $discrepancies);
        $this->compareSourcePostings($customer, $discrepancies);
        $this->comparePaymentAllocations($customer, $discrepancies);

        return [
            'shop_id' => $shopId,
            'customer_id' => (int) $customer->getKey(),
            'consistent' => $discrepancies === [],
            'discrepancies' => $discrepancies,
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $discrepancies
     */
    private function compareLastFinancialEvent(Customer $customer, ?CustomerAccount $account, array &$discrepancies): void
    {
        $expected = DB::table('financial_transactions')
            ->where('shop_id', $customer->shop_id)
            ->where('customer_id', $customer->getKey())
            ->max('occurred_at');
        $expected = $expected === null
            ? null
            : CarbonImmutable::parse($expected)->format('Y-m-d H:i:s.u');
        $actual = $account?->last_financial_event_at?->format('Y-m-d H:i:s.u');

        if ($actual !== $expected) {
            $discrepancies[] = [
                'type' => 'last_financial_event_mismatch',
                'expected' => $expected,
                'actual' => $actual,
            ];
        }
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

            $expectedSourceId = LedgerEntry::query()
                ->where('shop_id', $customer->shop_id)
                ->where('customer_id', $customer->getKey())
                ->where('effective_date', '<=', $date)
                ->orderByDesc('effective_date')
                ->orderByDesc('id')
                ->value('id');

            if ((int) ($snapshot->source_ledger_entry_id ?? 0) !== (int) ($expectedSourceId ?? 0)) {
                $discrepancies[] = [
                    'type' => 'balance_snapshot_source_mismatch',
                    'as_of_date' => $date,
                    'expected' => $expectedSourceId,
                    'actual' => $snapshot->source_ledger_entry_id,
                ];
            }
        }
    }

    /**
     * Verify normal and reversal allocation headers/items against their
     * consolidated Payment amount. Sale-day automatic Payments are intentionally
     * sale-restricted and do not use normal FIFO allocation records.
     *
     * @param  list<array<string,mixed>>  $discrepancies
     */
    private function comparePaymentAllocations(Customer $customer, array &$discrepancies): void
    {
        foreach (Payment::query()
            ->where('shop_id', $customer->shop_id)
            ->where('customer_id', $customer->getKey())
            ->whereIn('payment_type', [Payment::TYPE_NORMAL, Payment::TYPE_REVERSAL])
            ->whereNotNull('posted_at')
            ->get(['id', 'amount', 'payment_type']) as $payment) {
            $allocation = DB::table('payment_allocations')
                ->where('shop_id', $customer->shop_id)
                ->where('customer_id', $customer->getKey())
                ->where('payment_id', $payment->getKey())
                ->first();

            if ($allocation === null) {
                $discrepancies[] = [
                    'type' => 'payment_allocation_missing',
                    'payment_id' => (int) $payment->getKey(),
                ];

                continue;
            }

            $itemsTotal = (string) DB::table('payment_allocation_items')
                ->where('shop_id', $customer->shop_id)
                ->where('customer_id', $customer->getKey())
                ->where('allocation_id', $allocation->id)
                ->sum('amount');
            $expected = BigDecimal::of((string) $payment->amount);

            foreach ([
                'allocation_header_mismatch' => (string) $allocation->total_allocated,
                'allocation_items_mismatch' => $itemsTotal ?: '0.00',
            ] as $type => $actual) {
                if (BigDecimal::of($actual)->compareTo($expected) !== 0) {
                    $discrepancies[] = [
                        'type' => $type,
                        'payment_id' => (int) $payment->getKey(),
                        'expected' => (string) $expected,
                        'actual' => $actual,
                    ];
                }
            }

            $expectedGroups = [];
            foreach (DB::table('payment_allocation_items')
                ->where('shop_id', $customer->shop_id)
                ->where('customer_id', $customer->getKey())
                ->where('allocation_id', $allocation->id)
                ->get(['sale_id', 'component_type', 'amount']) as $item) {
                $component = match ($item->component_type) {
                    'PRINCIPAL', 'CAPITALIZED_INTEREST_PRINCIPAL' => 'PRINCIPAL',
                    'NON_CAPITALIZED_INTEREST' => 'INTEREST',
                    default => null,
                };

                if ($component === null) {
                    $discrepancies[] = [
                        'type' => 'payment_allocation_component_unknown',
                        'payment_id' => (int) $payment->getKey(),
                        'component_type' => $item->component_type,
                    ];

                    continue;
                }

                $key = $item->sale_id.'|'.$component;
                $expectedGroups[$key] = ($expectedGroups[$key] ?? BigDecimal::zero())
                    ->plus((string) $item->amount);
            }

            $entryKind = $payment->payment_type === Payment::TYPE_REVERSAL ? 'REVERSAL' : 'PAYMENT';
            $actualGroups = [];
            foreach (LedgerEntry::query()
                ->where('shop_id', $customer->shop_id)
                ->where('customer_id', $customer->getKey())
                ->where('payment_id', $payment->getKey())
                ->where('entry_kind', $entryKind)
                ->get(['sale_id', 'component', 'debit_amount', 'credit_amount']) as $entry) {
                $key = $entry->sale_id.'|'.$entry->component;
                $amount = $payment->payment_type === Payment::TYPE_REVERSAL
                    ? BigDecimal::of((string) $entry->debit_amount)->minus((string) $entry->credit_amount)
                    : BigDecimal::of((string) $entry->credit_amount)->minus((string) $entry->debit_amount);
                $actualGroups[$key] = ($actualGroups[$key] ?? BigDecimal::zero())->plus($amount);
            }

            foreach (array_unique([...array_keys($expectedGroups), ...array_keys($actualGroups)]) as $key) {
                $expectedAmount = $expectedGroups[$key] ?? BigDecimal::zero();
                $actualAmount = $actualGroups[$key] ?? BigDecimal::zero();

                if ($expectedAmount->compareTo($actualAmount) !== 0) {
                    $discrepancies[] = [
                        'type' => 'payment_allocation_ledger_mismatch',
                        'payment_id' => (int) $payment->getKey(),
                        'sale_component' => $key,
                        'expected' => (string) $expectedAmount->toScale(2),
                        'actual' => (string) $actualAmount->toScale(2),
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
