<?php

namespace App\Modules\Financial\Application;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\FinancialTransaction;
use App\Models\Sale;
use App\Models\SaleRevision;
use App\Models\User;
use App\Tenancy\CurrentShop;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class FinancialAuditService
{
    public function __construct(private readonly CurrentShop $currentShop) {}

    /**
     * Append an audit event linked to the immutable financial transaction header.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function recordTransaction(
        User $actor,
        Customer $customer,
        FinancialTransaction $transaction,
        string $action,
        array $newValues,
        ?array $oldValues = null,
        ?string $reason = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AuditLog {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Financial audit records must be written inside the financial database transaction.');
        }

        $shopId = (int) $this->currentShop->require()->getKey();

        if (
            (int) $actor->shop_id !== $shopId
            || (int) $customer->shop_id !== $shopId
            || (int) $transaction->shop_id !== $shopId
            || (int) $transaction->customer_id !== (int) $customer->getKey()
        ) {
            throw new AuthorizationException('The financial audit context does not belong to the current shop and customer.');
        }

        if (trim($action) === '' || mb_strlen($action) > 100) {
            throw new InvalidArgumentException('The financial audit action must contain between 1 and 100 characters.');
        }

        if ($reason !== null && mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('The financial audit reason cannot exceed 500 characters.');
        }

        if ($ipAddress !== null && mb_strlen($ipAddress) > 45) {
            throw new InvalidArgumentException('The audit IP address is invalid.');
        }

        if ($userAgent !== null && mb_strlen($userAgent) > 500) {
            throw new InvalidArgumentException('The audit user agent cannot exceed 500 characters.');
        }

        return AuditLog::query()->create([
            'user_id' => $actor->getKey(),
            'action' => $action,
            'entity_type' => 'financial_transaction',
            'entity_id' => $transaction->getKey(),
            'reason' => $reason,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    public function recordSaleRevision(
        User $actor,
        Customer $customer,
        Sale $sale,
        SaleRevision $revision,
    ): AuditLog {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Sale revision audit records must be written inside the financial database transaction.');
        }

        $shopId = (int) $this->currentShop->require()->getKey();

        if (
            (int) $actor->shop_id !== $shopId
            || (int) $customer->shop_id !== $shopId
            || (int) $sale->shop_id !== $shopId
            || (int) $sale->customer_id !== (int) $customer->getKey()
            || (int) $revision->shop_id !== $shopId
            || (int) $revision->sale_id !== (int) $sale->getKey()
        ) {
            throw new AuthorizationException('The Sale revision audit context does not belong to the current shop and customer.');
        }

        return AuditLog::query()->create([
            'user_id' => $actor->getKey(),
            'action' => 'financial.sale_revised',
            'entity_type' => 'sale_revision',
            'entity_id' => $revision->getKey(),
            'reason' => $revision->correction_reason,
            'old_values' => $revision->old_values,
            'new_values' => $revision->new_values,
        ]);
    }
}
