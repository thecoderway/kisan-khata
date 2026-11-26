<?php

namespace App\Modules\Financial\Application;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\FinancialTransaction;
use App\Models\InterestLotEvent;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentAllocationItem;
use App\Models\PaymentRevision;
use App\Models\Sale;
use App\Models\User;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PaymentReversalService
{
    public function __construct(
        private readonly FinancialTransactionService $transactions,
        private readonly FinancialAuditService $audit,
        private readonly PaymentPostingService $payments,
    ) {}

    /**
     * @param array{amount:string|int,payment_method?:?string,reference_number?:?string,description?:?string}|null $replacement
     * @return array{reversal:Payment,replacement:?Payment}
     */
    public function correct(
        User $actor,
        Customer $customer,
        Payment $payment,
        string $reason,
        string $idempotencyKey,
        ?array $replacement = null,
    ): array {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('A correction reason between 1 and 500 characters is required.');
        }

        if ($actor->role !== User::ROLE_OWNER || ! $actor->is_active) {
            throw new AuthorizationException('Only an active Shop Owner may correct a posted Payment.');
        }

        if ((int) $payment->customer_id !== (int) $customer->getKey()
            || (int) $payment->shop_id !== (int) $customer->shop_id) {
            throw new AuthorizationException('The Payment does not belong to this Customer.');
        }

        if ($replacement !== null && ! array_key_exists('amount', $replacement)) {
            throw new InvalidArgumentException('A replacement Payment amount is required.');
        }

        $paymentDate = $payment->payment_date->format('Y-m-d');
        $payload = [
            'payment_id' => (int) $payment->getKey(),
            'reason' => $reason,
            'replacement' => $replacement,
        ];

        $transaction = $this->transactions->executeForCustomer(
            $actor,
            $customer,
            FinancialTransaction::TYPE_REVERSAL,
            $idempotencyKey,
            $payload,
            function (CustomerAccount $account, $key) use ($actor, $customer, $payment, $paymentDate, $reason, $replacement): FinancialTransaction {
                $original = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
                if ($original->payment_type !== Payment::TYPE_NORMAL) {
                    throw new ConflictHttpException('Only a normal customer Payment can be corrected.');
                }

                if (Payment::query()->where('reversal_of_payment_id', $original->getKey())->exists()) {
                    throw new ConflictHttpException('This Payment has already been reversed.');
                }

                $allocation = PaymentAllocation::query()
                    ->where('payment_id', $original->getKey())
                    ->where('allocation_type', PaymentAllocation::TYPE_NORMAL_FIFO)
                    ->lockForUpdate()
                    ->first();
                if ($allocation === null) {
                    throw new ConflictHttpException('The original Payment has no reversible FIFO allocation.');
                }

                $this->assertNoDependentActivity($customer, $original, $paymentDate);

                $items = PaymentAllocationItem::query()
                    ->where('allocation_id', $allocation->getKey())
                    ->orderBy('sale_fifo_sequence')
                    ->orderBy('component_sequence')
                    ->orderBy('lot_sequence')
                    ->lockForUpdate()
                    ->get();
                $originalEntries = LedgerEntry::query()
                    ->where('payment_id', $original->getKey())
                    ->where('entry_kind', 'PAYMENT')
                    ->orderBy('sequence_number')
                    ->lockForUpdate()
                    ->get();

                $entryTotal = BigDecimal::zero();
                foreach ($originalEntries as $entry) {
                    $entryTotal = $entryTotal->plus($entry->credit_amount)->minus($entry->debit_amount);
                }
                $allocationTotal = BigDecimal::zero();
                foreach ($items as $item) {
                    $allocationTotal = $allocationTotal->plus($item->amount);
                }
                if ($entryTotal->compareTo((string) $original->amount) !== 0
                    || $allocationTotal->compareTo((string) $original->amount) !== 0
                    || BigDecimal::of((string) $allocation->total_allocated)->compareTo((string) $original->amount) !== 0
                    || $items->isEmpty()) {
                    throw new LogicException('Original Payment ledger and allocation do not reconcile to its posted amount.');
                }

                $reversalTransaction = $this->transactions->createHeader(
                    $actor,
                    $customer,
                    $key,
                    FinancialTransaction::TYPE_REVERSAL,
                    new DateTimeImmutable($paymentDate),
                    'payment',
                    (int) $original->getKey(),
                );

                $now = now();
                $reversal = new Payment;
                $reversal->forceFill([
                    'shop_id' => $customer->shop_id,
                    'customer_id' => $customer->getKey(),
                    'payment_number' => 'PAY-'.Str::ulid(),
                    'payment_type' => Payment::TYPE_REVERSAL,
                    'source_sale_id' => null,
                    'payment_date' => $paymentDate,
                    'amount' => $original->amount,
                    'payment_method' => $original->payment_method,
                    'reference_number' => $original->reference_number,
                    'description' => 'Reversal of '.$original->payment_number.': '.$reason,
                    'financial_transaction_id' => $reversalTransaction->getKey(),
                    'reversal_of_payment_id' => $original->getKey(),
                    'created_by' => $actor->getKey(),
                    'posted_by' => $actor->getKey(),
                    'posted_at' => $now,
                    'created_at' => $now,
                ]);
                $reversal->save();

                $reversalAllocation = PaymentAllocation::query()->create([
                    'customer_id' => $customer->getKey(),
                    'payment_id' => $reversal->getKey(),
                    'allocation_type' => PaymentAllocation::TYPE_REVERSAL,
                    'waterfall_policy_version_id' => $allocation->waterfall_policy_version_id,
                    'allocation_effective_at' => $now,
                    'total_allocated' => $original->amount,
                    'reversal_of_allocation_id' => $allocation->getKey(),
                ]);

                $sequence = 0;
                foreach ($items as $item) {
                    $reversedItem = PaymentAllocationItem::query()->create([
                        'customer_id' => $customer->getKey(),
                        'allocation_id' => $reversalAllocation->getKey(),
                        'payment_id' => $reversal->getKey(),
                        'sale_id' => $item->sale_id,
                        'component_type' => $item->component_type,
                        'source_interest_lot_id' => $item->source_interest_lot_id,
                        'amount' => $item->amount,
                        'sale_fifo_sequence' => $item->sale_fifo_sequence,
                        'component_sequence' => $item->component_sequence,
                        'lot_sequence' => $item->lot_sequence,
                        'reverses_allocation_item_id' => $item->getKey(),
                    ]);

                    if ($item->source_interest_lot_id !== null) {
                        $event = DB::table('interest_lot_events')
                            ->where('allocation_item_id', $item->getKey())
                            ->where('event_type', 'ALLOCATED')
                            ->first();
                        if ($event === null) {
                            throw new LogicException('The original interest allocation event is missing.');
                        }

                        InterestLotEvent::query()->create([
                            'customer_id' => $customer->getKey(),
                            'interest_lot_id' => $item->source_interest_lot_id,
                            'event_type' => 'REVERSED',
                            'event_date' => $paymentDate,
                            'amount' => $item->amount,
                            'financial_transaction_id' => $reversalTransaction->getKey(),
                            'allocation_item_id' => $reversedItem->getKey(),
                            'reverses_event_id' => $event->id,
                            'event_key_hash' => hash('sha256', 'payment-reversal|'.$reversal->getKey().'|'.$item->getKey()),
                        ]);
                    }
                }

                foreach ($originalEntries as $entry) {
                    $sequence++;
                    LedgerEntry::query()->create([
                        'customer_id' => $customer->getKey(),
                        'financial_transaction_id' => $reversalTransaction->getKey(),
                        'sale_id' => $entry->sale_id,
                        'payment_id' => $reversal->getKey(),
                        'component' => $entry->component,
                        'entry_kind' => 'REVERSAL',
                        'debit_amount' => $entry->credit_amount,
                        'credit_amount' => $entry->debit_amount,
                        'effective_date' => $paymentDate,
                        'sequence_number' => $sequence,
                        'reverses_entry_id' => $entry->getKey(),
                    ]);
                }

                $revisionNumber = ((int) PaymentRevision::query()
                    ->where('payment_id', $original->getKey())
                    ->max('revision_number')) + 1;

                $reversalRevision = PaymentRevision::query()->create([
                    'customer_id' => $customer->getKey(),
                    'payment_id' => $original->getKey(),
                    'revision_number' => $revisionNumber,
                    'revision_type' => PaymentRevision::TYPE_REVERSAL,
                    'old_values' => $this->paymentSnapshot($original),
                    'new_values' => null,
                    'correction_reason' => $reason,
                    'changed_by' => $actor->getKey(),
                    'changed_at' => $now,
                    'reversal_payment_id' => $reversal->getKey(),
                ]);

                $account->refresh();
                $this->refreshSaleStatuses($customer);
                $this->refreshAccountProjection($account, $customer);

                $this->audit->recordTransaction(
                    $actor,
                    $customer,
                    $reversalTransaction,
                    'financial.payment_reversed',
                    [
                        'original_payment_id' => $original->getKey(),
                        'reversal_payment_id' => $reversal->getKey(),
                        'allocation_id' => $allocation->getKey(),
                        'reversal_allocation_id' => $reversalAllocation->getKey(),
                        'revision_id' => $reversalRevision->getKey(),
                        'amount' => $original->amount,
                        'reason' => $reason,
                    ],
                    $this->paymentSnapshot($original),
                    $reason,
                );

                if ($replacement !== null) {
                    $replacementPayment = $this->payments->post(
                        $actor,
                        $customer,
                        $replacement['amount'],
                        new DateTimeImmutable($paymentDate),
                        $replacement['payment_method'] ?? $original->payment_method,
                        $replacement['reference_number'] ?? null,
                        $replacement['description'] ?? $original->description,
                        'replacement-'.hash('sha256', (string) $key->getKey()),
                        (int) $original->getKey(),
                    );

                    $nextRevisionNumber = $revisionNumber + 1;
                    PaymentRevision::query()->create([
                        'customer_id' => $customer->getKey(),
                        'payment_id' => $original->getKey(),
                        'revision_number' => $nextRevisionNumber,
                        'revision_type' => PaymentRevision::TYPE_REPLACEMENT,
                        'old_values' => $this->paymentSnapshot($original),
                        'new_values' => [
                            'payment_id' => $replacementPayment->getKey(),
                            'amount' => $replacementPayment->amount,
                            'payment_date' => $replacementPayment->payment_date->format('Y-m-d'),
                            'payment_method' => $replacementPayment->payment_method,
                            'reference_number' => $replacementPayment->reference_number,
                            'description' => $replacementPayment->description,
                        ],
                        'correction_reason' => $reason,
                        'changed_by' => $actor->getKey(),
                        'changed_at' => now(),
                        'replacement_payment_id' => $replacementPayment->getKey(),
                    ]);
                }

                return $reversalTransaction;
            },
        );

        $reversal = Payment::query()
            ->where('financial_transaction_id', $transaction->getKey())
            ->firstOrFail();

        $replacementPayment = Payment::query()
            ->where('replacement_for_payment_id', $payment->getKey())
            ->where('payment_type', Payment::TYPE_NORMAL)
            ->first();

        return ['reversal' => $reversal, 'replacement' => $replacementPayment];
    }

    private function assertNoDependentActivity(Customer $customer, Payment $payment, string $paymentDate): void
    {
        $laterPaymentExists = Payment::query()
            ->where('customer_id', $customer->getKey())
            ->where('payment_type', Payment::TYPE_NORMAL)
            ->where('id', '<>', $payment->getKey())
            ->where('payment_date', '>=', $paymentDate)
            ->exists();
        if ($laterPaymentExists) {
            throw new ConflictHttpException('This Payment cannot be reversed after another normal Payment on the same or a later date.');
        }

        $laterInterestRunExists = DB::table('interest_calculation_runs')
            ->where('shop_id', $customer->shop_id)
            ->where('customer_id', $customer->getKey())
            ->where('cutoff_date_exclusive', '>', $paymentDate)
            ->exists();
        if ($laterInterestRunExists) {
            throw new ConflictHttpException('This Payment cannot be reversed after a later interest checkpoint; historical interest restatement is not yet supported.');
        }

        $settlementExists = DB::table('interest_settlement_runs')
            ->where('shop_id', $customer->shop_id)
            ->where('customer_id', $customer->getKey())
            ->where('cutoff_date_exclusive', '>=', $paymentDate)
            ->where('status', 'COMPLETED')
            ->exists();
        if ($settlementExists) {
            throw new ConflictHttpException('This Payment cannot be reversed after an annual interest settlement at or after its effective date.');
        }
    }

    private function refreshSaleStatuses(Customer $customer): void
    {
        $sales = Sale::query()->where('customer_id', $customer->getKey())->whereNotNull('posted_at')->get();
        foreach ($sales as $sale) {
            $entries = LedgerEntry::query()->where('customer_id', $customer->getKey())->where('sale_id', $sale->getKey());
            $debits = (string) ((clone $entries)->sum('debit_amount') ?: '0.00');
            $credits = (string) ((clone $entries)->sum('credit_amount') ?: '0.00');
            $balance = BigDecimal::of($debits)->minus($credits)->toScale(2);
            $paymentCredits = (string) LedgerEntry::query()
                ->where('customer_id', $customer->getKey())
                ->where('sale_id', $sale->getKey())
                ->where('entry_kind', 'PAYMENT')
                ->sum('credit_amount');
            $reversalDebits = (string) LedgerEntry::query()
                ->where('customer_id', $customer->getKey())
                ->where('sale_id', $sale->getKey())
                ->where('entry_kind', 'REVERSAL')
                ->sum('debit_amount');
            $hasAnyNetPayment = BigDecimal::of($paymentCredits ?: '0.00')->minus($reversalDebits ?: '0.00')->isPositive();
            $status = $balance->isZero()
                ? Sale::PAYMENT_STATUS_PAID
                : ($hasAnyNetPayment ? Sale::PAYMENT_STATUS_PARTIALLY_PAID : Sale::PAYMENT_STATUS_UNPAID);

            DB::table('sales')->where('shop_id', $customer->shop_id)->where('customer_id', $customer->getKey())
                ->where('id', $sale->getKey())->update(['payment_status' => $status]);
        }
    }

    private function refreshAccountProjection(CustomerAccount $account, Customer $customer): void
    {
        $principal = LedgerEntry::query()->where('customer_id', $customer->getKey())->where('component', 'PRINCIPAL');
        $interest = LedgerEntry::query()->where('customer_id', $customer->getKey())->where('component', 'INTEREST');
        $principalBalance = BigDecimal::of((string) ((clone $principal)->sum('debit_amount') ?: '0.00'))
            ->minus((string) ((clone $principal)->sum('credit_amount') ?: '0.00'))->toScale(2);
        $interestBalance = BigDecimal::of((string) ((clone $interest)->sum('debit_amount') ?: '0.00'))
            ->minus((string) ((clone $interest)->sum('credit_amount') ?: '0.00'))->toScale(2);

        if ($principalBalance->isNegative() || $interestBalance->isNegative()) {
            throw new LogicException('Payment reversal produced a negative account balance.');
        }

        $account->forceFill([
            'principal_balance' => (string) $principalBalance,
            'interest_balance' => (string) $interestBalance,
            'total_payable' => (string) $principalBalance->plus($interestBalance)->toScale(2),
            'last_financial_event_at' => now(),
            'projection_version' => (int) $account->projection_version + 1,
        ])->save();
    }

    private function paymentSnapshot(Payment $payment): array
    {
        return [
            'payment_id' => $payment->getKey(),
            'payment_number' => $payment->payment_number,
            'payment_type' => $payment->payment_type,
            'payment_date' => $payment->payment_date->format('Y-m-d'),
            'amount' => $payment->amount,
            'payment_method' => $payment->payment_method,
            'reference_number' => $payment->reference_number,
            'description' => $payment->description,
        ];
    }
}
