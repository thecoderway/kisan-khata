<?php

namespace App\Modules\Financial\Application;

use App\Models\Customer;
use App\Models\FinancialTransaction;
use App\Models\IdempotencyKey;
use App\Models\User;
use App\Tenancy\CurrentShop;
use Closure;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

class FinancialTransactionService
{
    public function __construct(
        private readonly CurrentShop $currentShop,
        private readonly CustomerAccountLockService $customerAccountLock,
        private readonly IdempotencyService $idempotency,
    ) {}

    /**
     * Run a financial operation under the customer/account lock and idempotency boundary.
     *
     * @param  array<string, mixed>  $requestPayload
     */
    public function executeForCustomer(
        User $actor,
        Customer $customer,
        string $operationType,
        string $idempotencyKey,
        array $requestPayload,
        Closure $operation,
    ): FinancialTransaction {
        $this->assertCurrentOwnership($actor, $customer);

        return DB::transaction(function () use (
            $actor,
            $customer,
            $operationType,
            $idempotencyKey,
            $requestPayload,
            $operation,
        ): FinancialTransaction {
            $account = $this->customerAccountLock->lockForCustomer($customer);

            $fingerprint = [
                'actor_id' => (int) $actor->getKey(),
                'customer_id' => (int) $customer->getKey(),
                'payload' => $requestPayload,
            ];

            return $this->idempotency->execute(
                $operationType,
                $idempotencyKey,
                $fingerprint,
                function (IdempotencyKey $reservation) use ($operation, $account, $actor, $customer): FinancialTransaction {
                    $transaction = $operation($account, $reservation);

                    if ($transaction instanceof FinancialTransaction === false) {
                        throw new LogicException('A financial operation must return a FinancialTransaction.');
                    }

                    if (
                        (int) $transaction->shop_id !== (int) $this->currentShop->require()->getKey()
                        || (int) $transaction->customer_id !== (int) $customer->getKey()
                        || (int) $transaction->created_by !== (int) $actor->getKey()
                        || (int) $transaction->idempotency_key_id !== (int) $reservation->getKey()
                    ) {
                        throw new AuthorizationException('The financial transaction does not match its authorized context.');
                    }

                    return $transaction;
                },
            );
        }, 3);
    }

    public function createHeader(
        User $actor,
        Customer $customer,
        IdempotencyKey $idempotencyKey,
        string $transactionType,
        DateTimeInterface $effectiveDate,
        ?string $sourceType = null,
        ?int $sourceId = null,
    ): FinancialTransaction {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('A financial transaction header must be created inside the posting transaction.');
        }

        $this->assertCurrentOwnership($actor, $customer);

        if ((int) $idempotencyKey->shop_id !== (int) $this->currentShop->require()->getKey()) {
            throw new AuthorizationException('The idempotency reservation does not belong to the current shop.');
        }

        $allowedTypes = [
            FinancialTransaction::TYPE_SALE_POSTING,
            FinancialTransaction::TYPE_PAYMENT_POSTING,
            FinancialTransaction::TYPE_INTEREST_POSTING,
            FinancialTransaction::TYPE_CAPITALIZATION,
            FinancialTransaction::TYPE_REVERSAL,
        ];

        if (in_array($transactionType, $allowedTypes, true) === false) {
            throw new InvalidArgumentException('The financial transaction type is invalid.');
        }

        if (($sourceType === null) !== ($sourceId === null)) {
            throw new InvalidArgumentException('Source type and source ID must be provided together.');
        }

        if ($sourceType !== null && (trim($sourceType) === '' || mb_strlen($sourceType) > 64)) {
            throw new InvalidArgumentException('The financial transaction source type is invalid.');
        }

        if ($sourceId !== null && $sourceId < 1) {
            throw new InvalidArgumentException('The financial transaction source ID must be positive.');
        }

        $now = now();
        $shopId = (int) $this->currentShop->require()->getKey();

        $transaction = new FinancialTransaction;
        $transaction->forceFill([
            'shop_id' => $shopId,
            'customer_id' => $customer->getKey(),
            'transaction_number' => 'FT-'.Str::ulid(),
            'transaction_type' => $transactionType,
            'effective_date' => $effectiveDate->format('Y-m-d'),
            'occurred_at' => $now,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'idempotency_key_id' => $idempotencyKey->getKey(),
            'created_by' => $actor->getKey(),
            'posted_by' => $actor->getKey(),
            'posted_at' => $now,
            'created_at' => $now,
        ]);
        $transaction->save();

        return $transaction;
    }

    private function assertCurrentOwnership(User $actor, Customer $customer): void
    {
        $shopId = (int) $this->currentShop->require()->getKey();

        if (
            (int) $actor->shop_id !== $shopId
            || (int) $customer->shop_id !== $shopId
        ) {
            throw new AuthorizationException('The actor and customer must belong to the current shop.');
        }
    }
}
