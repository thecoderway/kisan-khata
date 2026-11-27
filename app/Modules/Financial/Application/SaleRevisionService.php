<?php

namespace App\Modules\Financial\Application;

use App\Models\Customer;
use App\Models\InterestSettlementRun;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleRevision;
use App\Models\User;
use App\Tenancy\CurrentShop;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SaleRevisionService
{
    public function __construct(
        private readonly CustomerAccountLockService $customerAccountLock,
        private readonly IdempotencyService $idempotency,
        private readonly FinancialAuditService $audit,
    ) {}

    /**
     * Revise only non-financial Sale description data under the approved scope.
     *
     * Sale amount is immutable. Sale date/backdated changes are excluded until
     * the Product Owner approves the associated historical recalculation rules.
     */
    public function reviseDescription(
        User $actor,
        Sale $sale,
        ?string $description,
        string $reason,
        string $idempotencyKey,
    ): SaleRevision {
        $currentShopId = app(CurrentShop::class)->id();

        if (
            ! $actor->is_active
            || $actor->role !== User::ROLE_OWNER
            || $currentShopId === null
            || (int) $actor->shop_id !== $currentShopId
            || (int) $sale->shop_id !== $currentShopId
        ) {
            throw new AuthorizationException('Only an active Owner of the current Shop may initiate a posted Sale revision.');
        }

        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('A Sale revision reason is required and cannot exceed 500 characters.');
        }

        $description = $description === null || trim($description) === '' ? null : trim($description);

        if ($description !== null && mb_strlen($description) > 5000) {
            throw new InvalidArgumentException('Sale description cannot exceed 5,000 characters.');
        }

        $customer = Customer::query()->whereKey($sale->customer_id)->firstOrFail();

        if ((int) $customer->shop_id !== $currentShopId || (int) $customer->getKey() !== (int) $sale->customer_id) {
            throw new AuthorizationException('The Sale customer does not belong to the current Shop.');
        }

        $fingerprint = [
            'actor_id' => (int) $actor->getKey(),
            'customer_id' => (int) $customer->getKey(),
            'sale_id' => (int) $sale->getKey(),
            'description' => $description,
            'reason' => $reason,
        ];

        $revision = DB::transaction(function () use (
            $actor,
            $customer,
            $sale,
            $description,
            $reason,
            $idempotencyKey,
            $fingerprint,
        ): SaleRevision {
            $this->customerAccountLock->lockForCustomer($customer);

            $lockedSale = Sale::query()
                ->whereKey($sale->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSale->posted_at === null) {
                throw new ConflictHttpException('Only a posted Sale can be revised.');
            }

            $revision = $this->idempotency->executeForResource(
                'SALE_REVISION',
                $idempotencyKey,
                $fingerprint,
                'sale_revision',
                function () use ($actor, $customer, $lockedSale, $description, $reason): SaleRevision {
                    if ($this->isLockedByLaterFinancialEvent($lockedSale)) {
                        throw new ConflictHttpException('This Sale is locked by a later normal Payment or annual Interest Auto Settlement.');
                    }

                    if ($lockedSale->description === $description) {
                        throw new ConflictHttpException('The revised Sale description must differ from the current value.');
                    }

                    $now = now();
                    $revisionNumber = ((int) $lockedSale->revisions()->max('revision_number')) + 1;
                    $oldValues = ['description' => $lockedSale->description];
                    $newValues = ['description' => $description];

                    $revision = SaleRevision::query()->create([
                        'sale_id' => $lockedSale->getKey(),
                        'revision_number' => $revisionNumber,
                        'old_values' => $oldValues,
                        'new_values' => $newValues,
                        'correction_reason' => $reason,
                        'changed_by' => $actor->getKey(),
                        'changed_at' => $now,
                    ]);

                    // The only permitted update is performed after the immutable revision is recorded,
                    // under the same transaction and row lock. Direct model updates remain blocked.
                    $updated = DB::table('sales')
                        ->where('shop_id', $lockedSale->shop_id)
                        ->where('customer_id', $customer->getKey())
                        ->where('id', $lockedSale->getKey())
                        ->update(['description' => $description]);

                    if ($updated !== 1) {
                        throw new LogicException('The controlled Sale revision did not update exactly one Sale.');
                    }

                    $lockedSale->forceFill(['description' => $description]);

                    $this->audit->recordSaleRevision($actor, $customer, $lockedSale, $revision);

                    return $revision;
                },
                200,
            );

            if ($revision instanceof SaleRevision === false) {
                throw new LogicException('The Sale revision idempotency record returned an unexpected resource.');
            }

            return $revision;
        }, 3);

        return $revision;
    }

    private function isLockedByLaterFinancialEvent(Sale $sale): bool
    {
        $hasLaterNormalPayment = Payment::query()
            ->where('customer_id', $sale->customer_id)
            ->where('payment_type', Payment::TYPE_NORMAL)
            ->where('payment_date', '>=', $sale->sale_date->toDateString())
            ->where('posted_at', '>', $sale->posted_at)
            ->exists();

        if ($hasLaterNormalPayment) {
            return true;
        }

        return InterestSettlementRun::query()
            ->where('customer_id', $sale->customer_id)
            ->where('settlement_date', '>=', $sale->sale_date->toDateString())
            ->where('status', 'COMPLETED')
            ->where('completed_at', '>', $sale->posted_at)
            ->exists();
    }
}
