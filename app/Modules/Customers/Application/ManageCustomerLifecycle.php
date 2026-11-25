<?php

namespace App\Modules\Customers\Application;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageCustomerLifecycle
{
    public function __construct(private readonly CustomerDeletionEligibility $eligibility) {}

    public function deactivate(User $actor, int $customerId, string $reason, ?string $ipAddress, ?string $userAgent): Customer
    {
        $this->requireReason($reason);

        return DB::transaction(function () use ($actor, $customerId, $reason, $ipAddress, $userAgent): Customer {
            $customer = $this->lockCustomer($customerId);
            Gate::forUser($actor)->authorize('deactivate', $customer);

            if ($customer->status !== Customer::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['status' => 'Only an active customer can be deactivated.']);
            }

            $old = ['status' => $customer->status];
            $customer->status = Customer::STATUS_INACTIVE;
            $customer->save();

            $this->audit($actor, $customer, 'customer.deactivated', $old, ['status' => $customer->status], $reason, $ipAddress, $userAgent);

            return $customer;
        });
    }

    public function reactivate(User $actor, int $customerId, ?string $reason, ?string $ipAddress, ?string $userAgent): Customer
    {
        return DB::transaction(function () use ($actor, $customerId, $reason, $ipAddress, $userAgent): Customer {
            $customer = $this->lockCustomer($customerId);
            Gate::forUser($actor)->authorize('reactivate', $customer);

            if ($customer->status !== Customer::STATUS_INACTIVE) {
                throw ValidationException::withMessages(['status' => 'Only an inactive customer can be reactivated.']);
            }

            $old = ['status' => $customer->status];
            $customer->status = Customer::STATUS_ACTIVE;
            $customer->save();

            $this->audit($actor, $customer, 'customer.reactivated', $old, ['status' => $customer->status], $reason, $ipAddress, $userAgent);

            return $customer;
        });
    }

    public function softDelete(User $actor, int $customerId, string $reason, ?string $ipAddress, ?string $userAgent): Customer
    {
        $this->requireReason($reason);

        return DB::transaction(function () use ($actor, $customerId, $reason, $ipAddress, $userAgent): Customer {
            $customer = $this->lockCustomer($customerId);
            Gate::forUser($actor)->authorize('softDelete', $customer);

            $eligibility = $this->eligibility->check($customer);

            if (! $eligibility['eligible']) {
                throw ValidationException::withMessages(['customer' => $eligibility['reason']]);
            }

            $oldStatus = $customer->status;
            $customer->delete();

            $this->audit(
                $actor,
                $customer,
                'customer.soft_deleted',
                ['state' => $oldStatus],
                ['state' => 'soft_deleted', 'preserved_status' => $oldStatus, 'deleted_at' => $customer->deleted_at?->toISOString()],
                $reason,
                $ipAddress,
                $userAgent,
            );

            return $customer;
        });
    }

    public function restore(User $actor, int $customerId, ?string $ipAddress, ?string $userAgent): Customer
    {
        return DB::transaction(function () use ($actor, $customerId, $ipAddress, $userAgent): Customer {
            $customer = $this->lockCustomer($customerId, withTrashed: true);
            Gate::forUser($actor)->authorize('restore', $customer);

            if (! $customer->trashed()) {
                throw ValidationException::withMessages(['status' => 'Only a soft-deleted customer can be restored.']);
            }

            if ($customer->customer_code !== null && Customer::withTrashed()
                ->where('customer_code', $customer->customer_code)
                ->whereKeyNot($customer->getKey())
                ->exists()) {
                throw ValidationException::withMessages(['customer_code' => 'This customer code is already in use.']);
            }

            $previousStatus = $customer->status;
            $customer->restore();

            $this->audit(
                $actor,
                $customer,
                'customer.restored',
                ['state' => 'soft_deleted', 'preserved_status' => $previousStatus],
                ['state' => $previousStatus, 'deleted_at' => null],
                null,
                $ipAddress,
                $userAgent,
            );

            return $customer;
        });
    }

    private function lockCustomer(int $customerId, bool $withTrashed = false): Customer
    {
        $query = $withTrashed ? Customer::withTrashed() : Customer::query();

        return $query->whereKey($customerId)->lockForUpdate()->firstOrFail();
    }

    private function requireReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required for this action.']);
        }
    }

    private function audit(
        User $actor,
        Customer $customer,
        string $action,
        array $oldValues,
        array $newValues,
        ?string $reason,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        AuditLog::create([
            'user_id' => $actor->getKey(),
            'action' => $action,
            'entity_type' => 'customer',
            'entity_id' => $customer->getKey(),
            'reason' => $reason,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}
