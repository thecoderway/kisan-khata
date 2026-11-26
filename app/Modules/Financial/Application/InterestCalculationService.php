<?php

namespace App\Modules\Financial\Application;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\FinancialTransaction;
use App\Models\InterestCalculationItem;
use App\Models\InterestCalculationRun;
use App\Models\InterestLot;
use App\Models\InterestLotEvent;
use App\Models\InterestSettlementItem;
use App\Models\InterestSettlementRun;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class InterestCalculationService
{
    public const TRIGGER_CUSTOMER_PAYMENT = 'CUSTOMER_PAYMENT';

    public const TRIGGER_ANNUAL_AUTO_SETTLEMENT = 'ANNUAL_AUTO_SETTLEMENT';

    public function __construct(
        private readonly FinancialTransactionService $transactions,
        private readonly InterestCalculationKernel $kernel,
        private readonly InterestPolicyResolver $policies,
        private readonly FinancialAuditService $audit,
    ) {}

    public function calculateForPayment(
        User $actor,
        Customer $customer,
        Payment $payment,
    ): InterestCalculationRun {
        if (
            (int) $payment->shop_id !== (int) $customer->shop_id
            || (int) $payment->customer_id !== (int) $customer->getKey()
            || $payment->payment_type !== Payment::TYPE_NORMAL
        ) {
            throw new DomainException('Interest calculation requires a normal payment belonging to this customer.');
        }

        return $this->calculate(
            $actor,
            $customer,
            self::TRIGGER_CUSTOMER_PAYMENT,
            $payment,
            null,
            $payment->payment_date,
        );
    }

    public function calculateForSettlement(
        User $actor,
        Customer $customer,
        InterestSettlementRun $settlement,
    ): InterestCalculationRun {
        if (
            (int) $settlement->shop_id !== (int) $customer->shop_id
            || (int) $settlement->customer_id !== (int) $customer->getKey()
        ) {
            throw new DomainException('Annual interest calculation requires a settlement belonging to this customer.');
        }

        if ($settlement->status === 'COMPLETED') {
            return InterestCalculationRun::query()
                ->where('settlement_run_id', $settlement->getKey())
                ->firstOrFail();
        }

        if ($settlement->status !== 'PENDING') {
            throw new DomainException('Only a pending annual settlement can be calculated.');
        }

        return $this->calculate(
            $actor,
            $customer,
            self::TRIGGER_ANNUAL_AUTO_SETTLEMENT,
            null,
            $settlement,
            $settlement->cutoff_date_exclusive,
        );
    }

    private function calculate(
        User $actor,
        Customer $customer,
        string $triggerType,
        ?Payment $payment,
        ?InterestSettlementRun $settlement,
        DateTimeInterface $cutoff,
    ): InterestCalculationRun {
        $cutoffDate = $cutoff->format('Y-m-d');
        $triggerId = $payment?->getKey() ?? $settlement?->getKey();
        $eventIdentity = implode('|', [$triggerType, $customer->getKey(), $triggerId, $cutoffDate]);
        $eventHash = hash('sha256', $eventIdentity);
        $idempotencyKey = 'interest-run-'.$eventHash;

        $transaction = $this->transactions->executeForCustomer(
            $actor,
            $customer,
            FinancialTransaction::TYPE_INTEREST_POSTING,
            $idempotencyKey,
            [
                'trigger_type' => $triggerType,
                'trigger_id' => (int) $triggerId,
                'cutoff_date_exclusive' => $cutoffDate,
            ],
            function (CustomerAccount $account, $key) use (
                $actor,
                $customer,
                $triggerType,
                $payment,
                $settlement,
                $cutoffDate,
                $eventHash,
            ): FinancialTransaction {
                $sourceType = $payment !== null ? 'payment' : 'interest_settlement_run';
                $sourceId = $payment?->getKey() ?? $settlement?->getKey();
                $transaction = $this->transactions->createHeader(
                    $actor,
                    $customer,
                    $key,
                    FinancialTransaction::TYPE_INTEREST_POSTING,
                    new DateTimeImmutable($cutoffDate),
                    $sourceType,
                    (int) $sourceId,
                );

                $run = InterestCalculationRun::query()->create([
                    'customer_id' => $customer->getKey(),
                    'trigger_type' => $triggerType,
                    'trigger_payment_id' => $payment?->getKey(),
                    'settlement_run_id' => $settlement?->getKey(),
                    'cutoff_date_exclusive' => $cutoffDate,
                    'event_key_hash' => $eventHash,
                    'started_at' => now(),
                    'status' => 'PENDING',
                    'idempotency_key_id' => $key->getKey(),
                ]);

                $sequence = 0;
                $principalIncrease = BigDecimal::zero();
                $interestIncrease = BigDecimal::zero();
                $sales = Sale::query()
                    ->where('customer_id', $customer->getKey())
                    ->where('sale_date', '<', $cutoffDate)
                    ->whereNotNull('posted_at')
                    ->orderBy('sale_date')
                    ->orderBy('id')
                    ->get();

                foreach ($sales as $sale) {
                    $this->calculateSale(
                        $customer,
                        $sale,
                        $run,
                        $transaction,
                        $settlement,
                        $cutoffDate,
                        $sequence,
                        $principalIncrease,
                        $interestIncrease,
                    );
                }

                $account->refresh();
                $newPrincipal = BigDecimal::of((string) $account->principal_balance)->plus($principalIncrease);
                $newInterest = BigDecimal::of((string) $account->interest_balance)->plus($interestIncrease);
                $account->forceFill([
                    'principal_balance' => (string) $newPrincipal->toScale(2),
                    'interest_balance' => (string) $newInterest->toScale(2),
                    'total_payable' => (string) $newPrincipal->plus($newInterest)->toScale(2),
                    'last_interest_cutoff_date' => $cutoffDate,
                    'last_financial_event_at' => now(),
                    'projection_version' => (int) $account->projection_version + 1,
                ])->save();

                $run->forceFill(['status' => 'COMPLETED', 'completed_at' => now()])->save();

                if ($settlement !== null) {
                    $settlement->forceFill(['status' => 'COMPLETED', 'completed_at' => now()])->save();
                }

                $this->audit->recordTransaction(
                    $actor,
                    $customer,
                    $transaction,
                    'financial.interest_calculated',
                    [
                        'calculation_run_id' => $run->getKey(),
                        'trigger_type' => $triggerType,
                        'cutoff_date_exclusive' => $cutoffDate,
                        'principal_increase' => (string) $principalIncrease->toScale(2),
                        'interest_increase' => (string) $interestIncrease->toScale(2),
                    ],
                );

                return $transaction;
            },
        );

        return InterestCalculationRun::query()
            ->where('idempotency_key_id', $transaction->idempotency_key_id)
            ->firstOrFail();
    }

    private function calculateSale(
        Customer $customer,
        Sale $sale,
        InterestCalculationRun $run,
        FinancialTransaction $transaction,
        ?InterestSettlementRun $settlement,
        string $cutoffDate,
        int &$sequence,
        BigDecimal &$principalIncrease,
        BigDecimal &$interestIncrease,
    ): void {
        $lastEnd = InterestCalculationItem::query()
            ->where('customer_id', $customer->getKey())
            ->where('sale_id', $sale->getKey())
            ->max('interval_end_date_exclusive');
        $startDate = $lastEnd === null ? $sale->sale_date->format('Y-m-d') : (string) $lastEnd;

        if ($startDate >= $cutoffDate) {
            return;
        }

        $boundaries = [$startDate, $cutoffDate];
        $boundaries = array_merge(
            $boundaries,
            $this->policyBoundaries((int) $customer->shop_id, (int) $customer->getKey(), $startDate, $cutoffDate),
            $this->principalEventBoundaries($customer, $sale, $startDate, $cutoffDate),
        );
        $boundaries = array_values(array_unique($boundaries));
        sort($boundaries, SORT_STRING);

        for ($index = 0; $index < count($boundaries) - 1; $index++) {
            $intervalStart = $boundaries[$index];
            $intervalEnd = $boundaries[$index + 1];

            if ($intervalStart >= $intervalEnd) {
                continue;
            }

            $principal = $this->principalBalanceAt($customer, $sale, $intervalStart);
            if ($principal->isNegative()) {
                throw new DomainException('A Sale has a negative principal ledger balance.');
            }

            $policy = $this->policies->resolve($customer, new DateTimeImmutable($intervalStart));
            $result = $this->kernel->calculate(
                $sale->sale_date,
                new DateTimeImmutable($intervalStart),
                new DateTimeImmutable($intervalEnd),
                (string) $principal->toScale(2),
                $policy['monthly_rate_percent'],
                $policy['grace_days'],
            );

            $itemHash = hash('sha256', implode('|', [
                $run->event_key_hash,
                $sale->getKey(),
                $intervalStart,
                $intervalEnd,
                $policy['shop_policy_version_id'],
                $policy['customer_policy_version_id'] ?? 'none',
            ]));

            $item = InterestCalculationItem::query()->create([
                'customer_id' => $customer->getKey(),
                'calculation_run_id' => $run->getKey(),
                'sale_id' => $sale->getKey(),
                'source_interest_lot_id' => null,
                'shop_policy_version_id' => $policy['shop_policy_version_id'],
                'customer_policy_version_id' => $policy['customer_policy_version_id'],
                'interval_start_date' => $result['interval_start_date'],
                'interval_end_date_exclusive' => $result['interval_end_date_exclusive'],
                'actual_days' => $result['actual_days'],
                'grace_days_applied' => $result['grace_days_applied'],
                'monthly_rate_percent' => $result['monthly_rate_percent'],
                'eligible_principal_basis' => $result['eligible_principal_basis'],
                'unrounded_interest' => $result['unrounded_interest'],
                'posted_interest' => $result['posted_interest'],
                'capitalization_enabled' => $policy['capitalization_enabled'],
                'item_key_hash' => $itemHash,
            ]);

            $amount = BigDecimal::of($result['posted_interest']);
            if ($amount->isZero()) {
                continue;
            }

            $lotType = $policy['capitalization_enabled'] ? 'CAPITALIZED_INTEREST' : 'NON_CAPITALIZED_INTEREST';
            $lot = InterestLot::query()->create([
                'customer_id' => $customer->getKey(),
                'sale_id' => $sale->getKey(),
                'calculation_item_id' => $item->getKey(),
                'lot_type' => $lotType,
                'lot_date' => $intervalEnd,
                'original_amount' => (string) $amount->toScale(2),
            ]);

            $sequence++;
            $interestEntry = LedgerEntry::query()->create([
                'customer_id' => $customer->getKey(),
                'financial_transaction_id' => $transaction->getKey(),
                'sale_id' => $sale->getKey(),
                'payment_id' => null,
                'component' => 'INTEREST',
                'entry_kind' => 'INTEREST',
                'debit_amount' => (string) $amount->toScale(2),
                'credit_amount' => '0.00',
                'effective_date' => $intervalEnd,
                'sequence_number' => $sequence,
            ]);

            if ($policy['capitalization_enabled']) {
                $sequence++;
                LedgerEntry::query()->create([
                    'customer_id' => $customer->getKey(),
                    'financial_transaction_id' => $transaction->getKey(),
                    'sale_id' => $sale->getKey(),
                    'payment_id' => null,
                    'component' => 'INTEREST',
                    'entry_kind' => 'CAPITALIZATION',
                    'debit_amount' => '0.00',
                    'credit_amount' => (string) $amount->toScale(2),
                    'effective_date' => $intervalEnd,
                    'sequence_number' => $sequence,
                ]);
                $sequence++;
                LedgerEntry::query()->create([
                    'customer_id' => $customer->getKey(),
                    'financial_transaction_id' => $transaction->getKey(),
                    'sale_id' => $sale->getKey(),
                    'payment_id' => null,
                    'component' => 'PRINCIPAL',
                    'entry_kind' => 'CAPITALIZATION',
                    'debit_amount' => (string) $amount->toScale(2),
                    'credit_amount' => '0.00',
                    'effective_date' => $intervalEnd,
                    'sequence_number' => $sequence,
                ]);
                $principalIncrease = $principalIncrease->plus($amount);
            } else {
                $interestIncrease = $interestIncrease->plus($amount);
            }

            InterestLotEvent::query()->create([
                'customer_id' => $customer->getKey(),
                'interest_lot_id' => $lot->getKey(),
                'event_type' => $policy['capitalization_enabled'] ? 'CAPITALIZED' : 'POSTED',
                'event_date' => $intervalEnd,
                'amount' => (string) $amount->toScale(2),
                'financial_transaction_id' => $transaction->getKey(),
                'event_key_hash' => hash('sha256', $itemHash.'|lot-event'),
            ]);

            if ($settlement !== null) {
                InterestSettlementItem::query()->create([
                    'customer_id' => $customer->getKey(),
                    'settlement_run_id' => $settlement->getKey(),
                    'calculation_item_id' => $item->getKey(),
                    'sale_id' => $sale->getKey(),
                    'interest_lot_id' => $lot->getKey(),
                    'posted_amount' => (string) $amount->toScale(2),
                    'ledger_entry_id' => $interestEntry->getKey(),
                ]);
            }
        }
    }

    private function principalBalanceAt(Customer $customer, Sale $sale, string $date): BigDecimal
    {
        $entries = LedgerEntry::query()
            ->where('customer_id', $customer->getKey())
            ->where('sale_id', $sale->getKey())
            ->where('component', 'PRINCIPAL')
            ->where('effective_date', '<=', $date);

        $debits = (string) ((clone $entries)->sum('debit_amount') ?: '0.00');
        $credits = (string) ((clone $entries)->sum('credit_amount') ?: '0.00');

        return BigDecimal::of($debits)->minus($credits)->toScale(2);
    }

    private function principalEventBoundaries(
        Customer $customer,
        Sale $sale,
        string $startDate,
        string $cutoffDate,
    ): array {
        return LedgerEntry::query()
            ->where('customer_id', $customer->getKey())
            ->where('sale_id', $sale->getKey())
            ->where('component', 'PRINCIPAL')
            ->where('effective_date', '>', $startDate)
            ->where('effective_date', '<', $cutoffDate)
            ->distinct()
            ->orderBy('effective_date')
            ->pluck('effective_date')
            ->map(fn ($date): string => (string) $date)
            ->all();
    }

    private function policyBoundaries(int $shopId, int $customerId, string $startDate, string $cutoffDate): array
    {
        $dates = [];
        $shopPolicy = DB::table('interest_policies')
            ->where('shop_id', $shopId)
            ->where('policy_code', InterestPolicyResolver::DEFAULT_POLICY_CODE)
            ->first();

        if ($shopPolicy === null) {
            throw new DomainException('The shop default interest policy has not been configured.');
        }

        $shopVersions = DB::table('interest_policy_versions')
            ->where('shop_id', $shopId)
            ->where('interest_policy_id', $shopPolicy->id)
            ->get(['effective_from', 'effective_to']);

        $customerVersions = DB::table('customer_interest_policy_versions')
            ->where('shop_id', $shopId)
            ->where('customer_id', $customerId)
            ->get(['effective_from', 'effective_to']);

        foreach ($shopVersions->concat($customerVersions) as $version) {
            foreach ([$version->effective_from, $version->effective_to] as $date) {
                if ($date !== null && $date > $startDate && $date < $cutoffDate) {
                    $dates[] = (string) $date;
                }
            }
        }

        return $dates;
    }
}
