<?php

namespace App\Modules\Financial\Application;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\FinancialTransaction;
use App\Models\IdempotencyKey;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Brick\Math\BigDecimal;
use DateTimeInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SalePostingService
{
    public function __construct(
        private readonly FinancialTransactionService $financialTransactions,
        private readonly IdempotencyService $idempotency,
        private readonly FinancialAuditService $audit,
        private readonly BalanceProjectionService $projections,
    ) {}

    public function post(
        User $actor,
        Customer $customer,
        string $paymentChoice,
        DateTimeInterface $saleDate,
        string|int $amount,
        ?string $description,
        string $idempotencyKey,
    ): Sale {
        $paymentChoice = strtoupper(trim($paymentChoice));

        if (in_array($paymentChoice, [Sale::PAYMENT_CHOICE_PAID, Sale::PAYMENT_CHOICE_UNPAID], true) === false) {
            throw new InvalidArgumentException('A Sale must be marked Paid or Unpaid.');
        }

        $normalizedAmount = $this->normalizeAmount($amount);
        $description = $description === null || trim($description) === '' ? null : trim($description);

        if ($description !== null && mb_strlen($description) > 5000) {
            throw new InvalidArgumentException('Sale description cannot exceed 5,000 characters.');
        }

        $effectiveDate = $saleDate->format('Y-m-d');

        $saleTransaction = $this->financialTransactions->executeForCustomer(
            $actor,
            $customer,
            'SALE_POSTING',
            $idempotencyKey,
            [
                'sale_payment_choice' => $paymentChoice,
                'sale_date' => $effectiveDate,
                'amount' => $normalizedAmount,
                'description' => $description,
            ],
            function (CustomerAccount $account, IdempotencyKey $key) use (
                $actor,
                $customer,
                $paymentChoice,
                $saleDate,
                $effectiveDate,
                $normalizedAmount,
                $description,
                $idempotencyKey,
            ): FinancialTransaction {
                $lockedCustomer = Customer::query()->whereKey($customer->getKey())->firstOrFail();

                if ($lockedCustomer->status !== Customer::STATUS_ACTIVE) {
                    throw new ConflictHttpException('Inactive customers cannot receive new Sales.');
                }

                $now = now();
                $saleTransaction = $this->financialTransactions->createHeader(
                    $actor,
                    $customer,
                    $key,
                    FinancialTransaction::TYPE_SALE_POSTING,
                    $saleDate,
                );

                $sale = new Sale;
                $sale->forceFill([
                    'shop_id' => $customer->shop_id,
                    'customer_id' => $customer->getKey(),
                    'sale_number' => 'SALE-'.Str::ulid(),
                    'sale_date' => $effectiveDate,
                    'amount' => $normalizedAmount,
                    'description' => $description,
                    'payment_status' => $paymentChoice === Sale::PAYMENT_CHOICE_PAID
                        ? Sale::PAYMENT_STATUS_PAID
                        : Sale::PAYMENT_STATUS_UNPAID,
                    'interest_settlement_status' => Sale::INTEREST_STATUS_PENDING,
                    'sale_payment_choice' => $paymentChoice,
                    'financial_transaction_id' => $saleTransaction->getKey(),
                    'created_by' => $actor->getKey(),
                    'posted_by' => $actor->getKey(),
                    'posted_at' => $now,
                    'created_at' => $now,
                ]);
                $sale->save();

                // Every posted Sale creates the authoritative principal debit.
                // A SALE_DAY_AUTO payment below offsets this with its own credit.
                $this->postLedgerEntry(
                    $customer,
                    $saleTransaction,
                    $sale,
                    null,
                    'SALE',
                    $normalizedAmount,
                    '0.00',
                    $effectiveDate,
                );
                $this->projections->rebuildLocked($account, $customer, $effectiveDate, (int) $saleTransaction->getKey());

                $this->audit->recordTransaction(
                    $actor,
                    $customer,
                    $saleTransaction,
                    'financial.sale_posted',
                    [
                        'sale_id' => $sale->getKey(),
                        'sale_number' => $sale->sale_number,
                        'sale_date' => $effectiveDate,
                        'amount' => $normalizedAmount,
                        'payment_choice' => $paymentChoice,
                        'payment_status' => $sale->payment_status,
                    ],
                );

                if ($paymentChoice === Sale::PAYMENT_CHOICE_PAID) {
                    $autoPaymentKey = hash('sha256', 'SALE_DAY_AUTO_PAYMENT|'.trim($idempotencyKey));

                    $this->idempotency->execute(
                        'PAYMENT_POSTING',
                        $autoPaymentKey,
                        [
                            'actor_id' => (int) $actor->getKey(),
                            'customer_id' => (int) $customer->getKey(),
                            'sale_id' => (int) $sale->getKey(),
                            'payment_type' => Payment::TYPE_SALE_DAY_AUTO,
                            'payment_date' => $effectiveDate,
                            'amount' => $normalizedAmount,
                        ],
                        function (IdempotencyKey $paymentKey) use (
                            $actor,
                            $customer,
                            $sale,
                            $saleDate,
                            $effectiveDate,
                            $normalizedAmount,
                            $now,
                        ): FinancialTransaction {
                            $paymentTransaction = $this->financialTransactions->createHeader(
                                $actor,
                                $customer,
                                $paymentKey,
                                FinancialTransaction::TYPE_PAYMENT_POSTING,
                                $saleDate,
                                'sale',
                                (int) $sale->getKey(),
                            );

                            $payment = new Payment;
                            $payment->forceFill([
                                'shop_id' => $customer->shop_id,
                                'customer_id' => $customer->getKey(),
                                'payment_number' => 'PAY-'.Str::ulid(),
                                'payment_type' => Payment::TYPE_SALE_DAY_AUTO,
                                'source_sale_id' => $sale->getKey(),
                                'payment_date' => $effectiveDate,
                                'amount' => $normalizedAmount,
                                'payment_method' => null,
                                'reference_number' => null,
                                'description' => 'Automatic sale-day payment for '.$sale->sale_number,
                                'financial_transaction_id' => $paymentTransaction->getKey(),
                                'created_by' => $actor->getKey(),
                                'posted_by' => $actor->getKey(),
                                'posted_at' => $now,
                                'created_at' => $now,
                            ]);
                            $payment->save();

                            $this->postLedgerEntry(
                                $customer,
                                $paymentTransaction,
                                $sale,
                                $payment,
                                'PAYMENT',
                                '0.00',
                                $normalizedAmount,
                                $effectiveDate,
                            );
                            $lockedAccount = CustomerAccount::query()
                                ->where('customer_id', $customer->getKey())
                                ->lockForUpdate()
                                ->firstOrFail();
                            $this->projections->rebuildLocked($lockedAccount, $customer, $effectiveDate, (int) $paymentTransaction->getKey());

                            $this->audit->recordTransaction(
                                $actor,
                                $customer,
                                $paymentTransaction,
                                'financial.sale_day_auto_payment_posted',
                                [
                                    'payment_id' => $payment->getKey(),
                                    'sale_id' => $sale->getKey(),
                                    'payment_type' => Payment::TYPE_SALE_DAY_AUTO,
                                    'payment_date' => $effectiveDate,
                                    'amount' => $normalizedAmount,
                                ],
                            );

                            return $paymentTransaction;
                        },
                    );
                }

                return $saleTransaction;
            },
        );

        return Sale::query()
            ->where('financial_transaction_id', $saleTransaction->getKey())
            ->firstOrFail();
    }

    private function postLedgerEntry(
        Customer $customer,
        FinancialTransaction $transaction,
        Sale $sale,
        ?Payment $payment,
        string $entryKind,
        string $debit,
        string $credit,
        string $effectiveDate,
    ): void {
        LedgerEntry::query()->create([
            'customer_id' => $customer->getKey(),
            'financial_transaction_id' => $transaction->getKey(),
            'sale_id' => $sale->getKey(),
            'payment_id' => $payment?->getKey(),
            'component' => 'PRINCIPAL',
            'entry_kind' => $entryKind,
            'debit_amount' => $debit,
            'credit_amount' => $credit,
            'effective_date' => $effectiveDate,
            'sequence_number' => 1,
        ]);
    }

    private function normalizeAmount(string|int $amount): string
    {
        $amount = trim((string) $amount);

        if (preg_match('/^\d{1,13}(?:\.\d{1,2})?$/D', $amount) !== 1) {
            throw new InvalidArgumentException('Sale amount must be a positive decimal with up to two fractional digits.');
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        $normalized = $whole.'.'.str_pad($fraction, 2, '0');

        if ($normalized === '0.00') {
            throw new InvalidArgumentException('Sale amount must be greater than zero.');
        }

        return $normalized;
    }
}
