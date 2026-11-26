<?php

namespace App\Modules\Financial\Application;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\FinancialTransaction;
use App\Models\InterestLot;
use App\Models\InterestLotEvent;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentAllocationItem;
use App\Models\Sale;
use App\Models\User;
use Brick\Math\BigDecimal;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PaymentPostingService
{
    public function __construct(
        private readonly FinancialTransactionService $transactions,
        private readonly InterestCalculationService $interest,
        private readonly InterestPolicyResolver $policies,
        private readonly FinancialAuditService $audit,
    ) {}

    public function post(
        User $actor,
        Customer $customer,
        string|int $amount,
        DateTimeInterface $paymentDate,
        ?string $paymentMethod,
        ?string $referenceNumber,
        ?string $description,
        string $idempotencyKey,
    ): Payment {
        $normalizedAmount = $this->normalizeAmount($amount);
        $paymentDateString = $paymentDate->format('Y-m-d');
        $paymentMethod = $this->normalizeOptional($paymentMethod, 32, 'Payment method');
        $referenceNumber = $this->normalizeOptional($referenceNumber, 128, 'Reference number');
        $description = $this->normalizeOptional($description, 5000, 'Payment description');

        $transaction = $this->transactions->executeForCustomer(
            $actor,
            $customer,
            FinancialTransaction::TYPE_PAYMENT_POSTING,
            $idempotencyKey,
            [
                'amount' => $normalizedAmount,
                'payment_date' => $paymentDateString,
                'payment_method' => $paymentMethod,
                'reference_number' => $referenceNumber,
                'description' => $description,
            ],
            function (CustomerAccount $account, $key) use (
                $actor,
                $customer,
                $normalizedAmount,
                $paymentDate,
                $paymentDateString,
                $paymentMethod,
                $referenceNumber,
                $description,
            ): FinancialTransaction {
                $lockedCustomer = Customer::query()->whereKey($customer->getKey())->firstOrFail();
                if ($lockedCustomer->status !== Customer::STATUS_ACTIVE) {
                    throw new ConflictHttpException('Inactive customers cannot post Payments.');
                }

                $paymentTransaction = $this->transactions->createHeader(
                    $actor,
                    $customer,
                    $key,
                    FinancialTransaction::TYPE_PAYMENT_POSTING,
                    $paymentDate,
                    'customer',
                    (int) $customer->getKey(),
                );

                $payment = new Payment;
                $payment->forceFill([
                    'shop_id' => $customer->shop_id,
                    'customer_id' => $customer->getKey(),
                    'payment_number' => 'PAY-'.Str::ulid(),
                    'payment_type' => Payment::TYPE_NORMAL,
                    'source_sale_id' => null,
                    'payment_date' => $paymentDateString,
                    'amount' => $normalizedAmount,
                    'payment_method' => $paymentMethod,
                    'reference_number' => $referenceNumber,
                    'description' => $description,
                    'financial_transaction_id' => $paymentTransaction->getKey(),
                    'created_by' => $actor->getKey(),
                    'posted_by' => $actor->getKey(),
                    'posted_at' => now(),
                    'created_at' => now(),
                ]);
                $payment->save();

                // This nested financial operation uses the same customer lock and
                // remains inside the outer transaction/savepoint boundary.
                $this->interest->calculateForPayment($actor, $customer, $payment);

                $account->refresh();
                $requested = BigDecimal::of($normalizedAmount);
                $policy = $this->policies->resolve($customer, $paymentDate);
                $allocationItems = $this->buildAllocationPlan($customer, $paymentDateString, $requested, $policy['payment_waterfall']);

                $plannedTotal = BigDecimal::zero();
                foreach ($allocationItems as $item) {
                    $plannedTotal = $plannedTotal->plus($item['amount']);
                }
                if ($plannedTotal->compareTo($requested) !== 0) {
                    throw new DomainException('The FIFO allocation plan does not equal the accepted Payment amount.');
                }

                $allocation = PaymentAllocation::query()->create([
                    'customer_id' => $customer->getKey(),
                    'payment_id' => $payment->getKey(),
                    'allocation_type' => PaymentAllocation::TYPE_NORMAL_FIFO,
                    'waterfall_policy_version_id' => $policy['shop_policy_version_id'],
                    'allocation_effective_at' => $payment->posted_at,
                    'total_allocated' => $normalizedAmount,
                ]);

                $sequence = 0;
                foreach ($allocationItems as $item) {
                    $allocationItem = PaymentAllocationItem::query()->create([
                        'customer_id' => $customer->getKey(),
                        'allocation_id' => $allocation->getKey(),
                        'payment_id' => $payment->getKey(),
                        'sale_id' => $item['sale_id'],
                        'component_type' => $item['component_type'],
                        'source_interest_lot_id' => $item['interest_lot_id'],
                        'amount' => $item['amount'],
                        'sale_fifo_sequence' => $item['sale_fifo_sequence'],
                        'component_sequence' => $item['component_sequence'],
                        'lot_sequence' => $item['lot_sequence'],
                    ]);

                    $sequence++;
                    LedgerEntry::query()->create([
                        'customer_id' => $customer->getKey(),
                        'financial_transaction_id' => $paymentTransaction->getKey(),
                        'sale_id' => $item['sale_id'],
                        'payment_id' => $payment->getKey(),
                        'component' => $item['ledger_component'],
                        'entry_kind' => 'PAYMENT',
                        'debit_amount' => '0.00',
                        'credit_amount' => $item['amount'],
                        'effective_date' => $paymentDateString,
                        'sequence_number' => $sequence,
                    ]);

                    if ($item['interest_lot_id'] !== null) {
                        InterestLotEvent::query()->create([
                            'customer_id' => $customer->getKey(),
                            'interest_lot_id' => $item['interest_lot_id'],
                            'event_type' => 'ALLOCATED',
                            'event_date' => $paymentDateString,
                            'amount' => $item['amount'],
                            'allocation_item_id' => $allocationItem->getKey(),
                            'event_key_hash' => hash('sha256', implode('|', [
                                $payment->getKey(), $allocationItem->getKey(), 'ALLOCATED',
                            ])),
                        ]);
                    }
                }

                $this->refreshSaleStatuses($customer, $paymentDateString);
                $this->refreshAccountProjection($account, $customer, $paymentDateString);

                $this->audit->recordTransaction(
                    $actor,
                    $customer,
                    $paymentTransaction,
                    'financial.payment_posted',
                    [
                        'payment_id' => $payment->getKey(),
                        'payment_number' => $payment->payment_number,
                        'payment_date' => $paymentDateString,
                        'amount' => $normalizedAmount,
                        'allocation_id' => $allocation->getKey(),
                        'allocation_item_count' => count($allocationItems),
                        'payment_waterfall' => $policy['payment_waterfall'],
                        'shop_policy_version_id' => $policy['shop_policy_version_id'],
                    ],
                );

                return $paymentTransaction;
            },
        );

        return Payment::query()
            ->where('financial_transaction_id', $transaction->getKey())
            ->firstOrFail();
    }

    /**
     * @return list<array{
     *   sale_id:int, component_type:string, ledger_component:string,
     *   interest_lot_id:int|null, amount:string, sale_fifo_sequence:int,
     *   component_sequence:int, lot_sequence:int
     * }>
     */
    private function buildAllocationPlan(
        Customer $customer,
        string $paymentDate,
        BigDecimal $requested,
        string $waterfall,
    ): array {
        $sales = Sale::query()
            ->where('customer_id', $customer->getKey())
            ->whereNotNull('posted_at')
            ->where('sale_date', '<=', $paymentDate)
            ->orderBy('sale_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $remainingPayment = $requested;
        $plan = [];

        foreach ($sales as $saleIndex => $sale) {
            $principal = $this->salePrincipalBalance($customer, $sale, $paymentDate);
            $lots = InterestLot::query()
                ->where('customer_id', $customer->getKey())
                ->where('sale_id', $sale->getKey())
                ->where('lot_date', '<=', $paymentDate)
                ->orderBy('lot_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $capitalizedLots = [];
            $nonCapitalizedLots = [];
            foreach ($lots as $lot) {
                $balance = $this->interestLotBalance($lot, $paymentDate);
                if ($balance->isZero()) {
                    continue;
                }
                if ($balance->isNegative()) {
                    throw new DomainException('An Interest Lot has a negative remaining balance.');
                }
                $entry = ['id' => (int) $lot->getKey(), 'balance' => $balance];
                if ($lot->lot_type === 'CAPITALIZED_INTEREST') {
                    $capitalizedLots[] = $entry;
                } else {
                    $nonCapitalizedLots[] = $entry;
                }
            }

            $components = [];
            $principalComponent = [
                'component_type' => PaymentAllocationItem::COMPONENT_PRINCIPAL,
                'ledger_component' => 'PRINCIPAL',
                'interest_lot_id' => null,
                'balance' => $principal,
                'lot_sequence' => 0,
            ];
            $capitalizedComponents = array_map(fn (array $lot, int $index): array => [
                'component_type' => PaymentAllocationItem::COMPONENT_CAPITALIZED_INTEREST_PRINCIPAL,
                'ledger_component' => 'PRINCIPAL',
                'interest_lot_id' => $lot['id'],
                'balance' => $lot['balance'],
                'lot_sequence' => $index + 1,
            ], $capitalizedLots, array_keys($capitalizedLots));
            $interestComponents = array_map(fn (array $lot, int $index): array => [
                'component_type' => PaymentAllocationItem::COMPONENT_NON_CAPITALIZED_INTEREST,
                'ledger_component' => 'INTEREST',
                'interest_lot_id' => $lot['id'],
                'balance' => $lot['balance'],
                'lot_sequence' => $index + 1,
            ], $nonCapitalizedLots, array_keys($nonCapitalizedLots));

            if ($waterfall === 'INTEREST_FIRST') {
                $components = array_merge($interestComponents, [$principalComponent], $capitalizedComponents);
            } else {
                $components = array_merge([$principalComponent], $capitalizedComponents, $interestComponents);
            }

            $componentSequence = 0;
            foreach ($components as $component) {
                if ($remainingPayment->isZero()) {
                    break 2;
                }
                if ($component['balance']->isZero()) {
                    continue;
                }

                $applied = $remainingPayment->isLessThan($component['balance'])
                    ? $remainingPayment
                    : $component['balance'];
                if ($applied->isZero()) {
                    continue;
                }

                $componentSequence++;
                $plan[] = [
                    'sale_id' => (int) $sale->getKey(),
                    'component_type' => $component['component_type'],
                    'ledger_component' => $component['ledger_component'],
                    'interest_lot_id' => $component['interest_lot_id'],
                    'amount' => (string) $applied->toScale(2),
                    'sale_fifo_sequence' => $saleIndex + 1,
                    'component_sequence' => $componentSequence,
                    'lot_sequence' => $component['lot_sequence'],
                ];
                $remainingPayment = $remainingPayment->minus($applied);
            }
        }

        if ($remainingPayment->isPositive()) {
            throw new ConflictHttpException('Payment exceeds eligible posted balances. Customer advances are not supported.');
        }

        return $plan;
    }

    private function salePrincipalBalance(Customer $customer, Sale $sale, string $effectiveDate): BigDecimal
    {
        if ($sale->sale_payment_choice === Sale::PAYMENT_CHOICE_PAID) {
            return BigDecimal::zero();
        }

        $originalPrincipal = (string) LedgerEntry::query()
            ->where('customer_id', $customer->getKey())
            ->where('sale_id', $sale->getKey())
            ->where('component', 'PRINCIPAL')
            ->where('entry_kind', 'SALE')
            ->where('effective_date', '<=', $effectiveDate)
            ->sum('debit_amount');

        // Capitalized-interest principal is tracked by its own Interest Lot.
        // Subtracting every PRINCIPAL ledger credit here would count a payment
        // against a capitalized lot twice (once here and once in lot balance).
        $allocatedOriginalPrincipal = (string) PaymentAllocationItem::query()
            ->where('customer_id', $customer->getKey())
            ->where('sale_id', $sale->getKey())
            ->where('component_type', PaymentAllocationItem::COMPONENT_PRINCIPAL)
            ->whereHas('payment', fn ($query) => $query->where('payment_date', '<=', $effectiveDate))
            ->sum('amount');

        return BigDecimal::of($originalPrincipal === '' ? '0.00' : $originalPrincipal)
            ->minus($allocatedOriginalPrincipal === '' ? '0.00' : $allocatedOriginalPrincipal)
            ->toScale(2);
    }

    private function interestLotBalance(InterestLot $lot, string $effectiveDate): BigDecimal
    {
        $allocated = (string) $lot->events()
            ->where('event_type', 'ALLOCATED')
            ->where('event_date', '<=', $effectiveDate)
            ->sum('amount');
        $reversed = (string) $lot->events()
            ->where('event_type', 'REVERSED')
            ->where('event_date', '<=', $effectiveDate)
            ->sum('amount');

        return BigDecimal::of((string) $lot->original_amount)
            ->minus($allocated === '' ? '0.00' : $allocated)
            ->plus($reversed === '' ? '0.00' : $reversed)
            ->toScale(2);
    }

    private function refreshSaleStatuses(Customer $customer, string $paymentDate): void
    {
        $sales = Sale::query()
            ->where('customer_id', $customer->getKey())
            ->whereNotNull('posted_at')
            ->where('sale_date', '<=', $paymentDate)
            ->get();

        foreach ($sales as $sale) {
            $entries = LedgerEntry::query()
                ->where('customer_id', $customer->getKey())
                ->where('sale_id', $sale->getKey());
            $debits = (string) ((clone $entries)->sum('debit_amount') ?: '0.00');
            $credits = (string) ((clone $entries)->sum('credit_amount') ?: '0.00');
            $balance = BigDecimal::of($debits)->minus($credits)->toScale(2);
            $paymentCredits = (string) LedgerEntry::query()
                ->where('customer_id', $customer->getKey())
                ->where('sale_id', $sale->getKey())
                ->where('entry_kind', 'PAYMENT')
                ->sum('credit_amount');
            $hasAnyCredit = BigDecimal::of($paymentCredits === '' ? '0.00' : $paymentCredits)->isPositive();
            $status = $balance->isZero()
                ? Sale::PAYMENT_STATUS_PAID
                : ($hasAnyCredit
                    ? Sale::PAYMENT_STATUS_PARTIALLY_PAID
                    : Sale::PAYMENT_STATUS_UNPAID);

            DB::table('sales')->where('id', $sale->getKey())->update([
                'payment_status' => $status,
                'interest_settlement_status' => Sale::INTEREST_STATUS_SETTLED,
            ]);
        }
    }

    private function refreshAccountProjection(
        CustomerAccount $account,
        Customer $customer,
        string $paymentDate,
    ): void {
        $principalEntries = LedgerEntry::query()
            ->where('customer_id', $customer->getKey())
            ->where('component', 'PRINCIPAL');
        $interestEntries = LedgerEntry::query()
            ->where('customer_id', $customer->getKey())
            ->where('component', 'INTEREST');

        $principal = BigDecimal::of((string) ((clone $principalEntries)->sum('debit_amount') ?: '0.00'))
            ->minus((string) ((clone $principalEntries)->sum('credit_amount') ?: '0.00'))
            ->toScale(2);
        $interest = BigDecimal::of((string) ((clone $interestEntries)->sum('debit_amount') ?: '0.00'))
            ->minus((string) ((clone $interestEntries)->sum('credit_amount') ?: '0.00'))
            ->toScale(2);

        if ($principal->isNegative() || $interest->isNegative()) {
            throw new DomainException('Ledger reconciliation produced a negative customer balance.');
        }

        $account->forceFill([
            'principal_balance' => (string) $principal,
            'interest_balance' => (string) $interest,
            'total_payable' => (string) $principal->plus($interest)->toScale(2),
            'last_interest_cutoff_date' => $paymentDate,
            'last_financial_event_at' => now(),
            'projection_version' => (int) $account->projection_version + 1,
        ])->save();
    }

    private function normalizeAmount(string|int $amount): string
    {
        $amount = trim((string) $amount);
        if (preg_match('/^\d{1,13}(?:\.\d{1,2})?$/D', $amount) !== 1) {
            throw new InvalidArgumentException('Payment amount must be a positive decimal with up to two fractional digits.');
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        $normalized = $whole.'.'.str_pad($fraction, 2, '0');
        if ($normalized === '0.00') {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        return $normalized;
    }

    private function normalizeOptional(?string $value, int $maxLength, string $label): ?string
    {
        $value = $value === null ? null : trim($value);
        if ($value === '') {
            return null;
        }
        if ($value !== null && mb_strlen($value) > $maxLength) {
            throw new InvalidArgumentException($label.' is too long.');
        }

        return $value;
    }
}
