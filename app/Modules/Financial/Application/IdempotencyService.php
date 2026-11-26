<?php

namespace App\Modules\Financial\Application;

use App\Models\FinancialTransaction;
use App\Models\IdempotencyKey;
use App\Tenancy\CurrentShop;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Closure;
use InvalidArgumentException;
use JsonException;
use LogicException;

class IdempotencyService
{
    private const RESOURCE_TYPE_FINANCIAL_TRANSACTION = 'financial_transaction';

    public function __construct(private readonly CurrentShop $currentShop) {}

    /**
     * Reserve an operation key and atomically complete it with its transaction.
     *
     * The caller must own the outer financial database transaction so the key,
     * financial transaction and related postings either all commit or all roll back.
     *
     * @param  array<string, mixed>  $requestPayload
     */
    public function execute(
        string $operationType,
        string $idempotencyKey,
        array $requestPayload,
        Closure $operation,
    ): FinancialTransaction
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Idempotency must execute inside the financial database transaction.');
        }

        $shopId = (int) $this->currentShop->require()->getKey();
        $operationType = trim($operationType);
        $idempotencyKey = trim($idempotencyKey);

        if (preg_match('/^[A-Z][A-Z0-9_.:-]{0,39}$/D', $operationType) !== 1) {
            throw new InvalidArgumentException('The idempotency operation type is invalid.');
        }

        if ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 255) {
            throw new InvalidArgumentException('The idempotency key must contain between 1 and 255 characters.');
        }

        $keyHash = hash('sha256', $idempotencyKey);
        $requestHash = $this->requestHash($requestPayload);

        try {
            $record = IdempotencyKey::query()->create([
                'operation_type' => $operationType,
                'key_hash' => $keyHash,
                'request_hash' => $requestHash,
                'status' => IdempotencyKey::STATUS_IN_PROGRESS,
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception) === false) {
                throw $exception;
            }

            $existing = IdempotencyKey::query()
                ->where('operation_type', $operationType)
                ->where('key_hash', $keyHash)
                ->lockForUpdate()
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            if (hash_equals($existing->request_hash, $requestHash) === false) {
                throw new ConflictHttpException('This idempotency key was already used for a different request.');
            }

            if (
                $existing->status !== IdempotencyKey::STATUS_COMPLETED
                || $existing->resource_type !== self::RESOURCE_TYPE_FINANCIAL_TRANSACTION
                || $existing->resource_id === null
            ) {
                throw new ConflictHttpException('The original request is not in a replayable completed state.');
            }

            $transaction = FinancialTransaction::query()->whereKey($existing->resource_id)->first();

            if ($transaction === null || (int) $transaction->idempotency_key_id !== (int) $existing->getKey()) {
                throw new LogicException('The completed idempotency record has no matching financial transaction.');
            }

            return $transaction;
        }

        $transaction = $operation($record);

        if ($transaction instanceof FinancialTransaction === false) {
            throw new LogicException('An idempotent financial operation must return a FinancialTransaction.');
        }

        if (
            (int) $transaction->shop_id !== $shopId
            || (int) $transaction->idempotency_key_id !== (int) $record->getKey()
        ) {
            throw new LogicException('The financial transaction is not linked to its idempotency reservation.');
        }

        $record->forceFill([
            'status' => IdempotencyKey::STATUS_COMPLETED,
            'resource_type' => self::RESOURCE_TYPE_FINANCIAL_TRANSACTION,
            'resource_id' => $transaction->getKey(),
            'response_code' => 201,
        ])->save();

        return $transaction;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    private function requestHash(array $payload): string
    {
        $canonical = $this->canonicalize($payload);
        $json = json_encode(
            $canonical,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );

        return hash('sha256', $json);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (is_array($value) === false) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return in_array($driverCode, [19, 1062], true)
            || str_starts_with((string) $exception->getCode(), '23');
    }
}
