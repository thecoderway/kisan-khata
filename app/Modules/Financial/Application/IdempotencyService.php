<?php

namespace App\Modules\Financial\Application;

use App\Models\FinancialTransaction;
use App\Models\IdempotencyKey;
use App\Models\PaymentRevision;
use App\Models\SaleRevision;
use App\Tenancy\CurrentShop;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class IdempotencyService
{
    private const RESOURCE_TYPE_FINANCIAL_TRANSACTION = 'financial_transaction';

    private const RESOURCE_MODELS = [
        'financial_transaction' => FinancialTransaction::class,
        'sale_revision' => SaleRevision::class,
        'payment_revision' => PaymentRevision::class,
    ];

    public function __construct(private readonly CurrentShop $currentShop) {}

    /**
     * @param  array<string, mixed>  $requestPayload
     */
    public function execute(
        string $operationType,
        string $idempotencyKey,
        array $requestPayload,
        Closure $operation,
    ): FinancialTransaction {
        $resource = $this->executeForResource(
            $operationType,
            $idempotencyKey,
            $requestPayload,
            self::RESOURCE_TYPE_FINANCIAL_TRANSACTION,
            $operation,
            201,
        );

        if ($resource instanceof FinancialTransaction === false) {
            throw new LogicException('The idempotent operation did not return a financial transaction.');
        }

        return $resource;
    }

    /**
     * Execute an idempotent operation whose result is an allow-listed append-only resource.
     *
     * @param  array<string, mixed>  $requestPayload
     */
    public function executeForResource(
        string $operationType,
        string $idempotencyKey,
        array $requestPayload,
        string $resourceType,
        Closure $operation,
        int $responseCode = 201,
    ): Model {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Idempotency must execute inside the financial database transaction.');
        }

        $shopId = (int) $this->currentShop->require()->getKey();
        $operationType = trim($operationType);
        $idempotencyKey = trim($idempotencyKey);
        $resourceClass = self::RESOURCE_MODELS[$resourceType] ?? null;

        if (preg_match('/^[A-Z][A-Z0-9_.:-]{0,39}$/D', $operationType) !== 1) {
            throw new InvalidArgumentException('The idempotency operation type is invalid.');
        }

        if ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 255) {
            throw new InvalidArgumentException('The idempotency key must contain between 1 and 255 characters.');
        }

        if ($resourceClass === null) {
            throw new InvalidArgumentException('The idempotency resource type is not supported.');
        }

        if ($responseCode < 200 || $responseCode > 599) {
            throw new InvalidArgumentException('The idempotency response code is invalid.');
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
                || $existing->resource_type !== $resourceType
                || $existing->resource_id === null
            ) {
                throw new ConflictHttpException('The original request is not in a replayable completed state.');
            }

            $resource = $resourceClass::query()->whereKey($existing->resource_id)->first();

            if ($resource === null || (int) $resource->shop_id !== $shopId) {
                throw new LogicException('The completed idempotency record has no matching tenant resource.');
            }

            if (
                $resource instanceof FinancialTransaction
                && (int) $resource->idempotency_key_id !== (int) $existing->getKey()
            ) {
                throw new LogicException('The completed idempotency record has no matching financial transaction.');
            }

            return $resource;
        }

        $resource = $operation($record);

        if (is_a($resource, $resourceClass) === false) {
            throw new LogicException('The idempotent operation returned an unexpected resource type.');
        }

        if ((int) $resource->shop_id !== $shopId) {
            throw new LogicException('The idempotent resource does not belong to the current shop.');
        }

        if (
            $resource instanceof FinancialTransaction
            && (int) $resource->idempotency_key_id !== (int) $record->getKey()
        ) {
            throw new LogicException('The financial transaction is not linked to its idempotency reservation.');
        }

        $record->forceFill([
            'status' => IdempotencyKey::STATUS_COMPLETED,
            'resource_type' => $resourceType,
            'resource_id' => $resource->getKey(),
            'response_code' => $responseCode,
        ])->save();

        return $resource;
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
