<?php

namespace App\Modules\Users\Application;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

class ManageShopUserLifecycle
{
    public function __construct(
        private readonly ShopUserCapacity $capacity,
    ) {}

    public function deactivate(User $actor, int $targetId, ?string $reason = null): User
    {
        return DB::transaction(function () use ($actor, $targetId, $reason): User {
            [$shop, $lockedActor, $target] = $this->lockContext($actor, $targetId);
            Gate::forUser($lockedActor)->authorize('deactivate', $target);

            if (! $target->is_active) {
                throw ValidationException::withMessages([
                    'user' => 'This user account is already deactivated.',
                ]);
            }

            $this->capacity->assertCanTransition($shop, $target, false, $target->role);

            $target->forceFill(['is_active' => false])->save();

            // Revoke every mobile token and every database-backed web session
            // in the same transaction as the account state and audit record.
            DB::table('personal_access_tokens')
                ->where('tokenable_type', $target->getMorphClass())
                ->where('tokenable_id', $target->getKey())
                ->delete();

            $sessionConnection = config('session.connection') ?: DB::getDefaultConnection();

            if ($sessionConnection !== DB::getDefaultConnection()) {
                throw new LogicException(
                    'Database-backed session revocation must use the application database connection '
                    .'so it remains atomic with account deactivation.'
                );
            }

            DB::connection($sessionConnection)
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $target->getKey())
                ->delete();

            $this->audit($shop, $lockedActor, $target, 'user.deactivated',
                ['is_active' => true], ['is_active' => false], $reason);

            return $target->refresh();
        });
    }

    public function reactivate(User $actor, int $targetId, ?string $reason = null): User
    {
        return DB::transaction(function () use ($actor, $targetId, $reason): User {
            [$shop, $lockedActor, $target] = $this->lockContext($actor, $targetId);
            Gate::forUser($lockedActor)->authorize('reactivate', $target);

            if ($target->is_active) {
                throw ValidationException::withMessages([
                    'user' => 'This user account is already active.',
                ]);
            }

            $this->capacity->assertCanTransition($shop, $target, true, $target->role);

            $target->forceFill(['is_active' => true])->save();

            $this->audit($shop, $lockedActor, $target, 'user.reactivated',
                ['is_active' => false], ['is_active' => true], $reason);

            return $target->refresh();
        });
    }

    public function changeRole(User $actor, int $targetId, string $newRole, ?string $reason = null): User
    {
        return DB::transaction(function () use ($actor, $targetId, $newRole, $reason): User {
            [$shop, $lockedActor, $target] = $this->lockContext($actor, $targetId);
            Gate::forUser($lockedActor)->authorize('changeRole', [$target, $newRole]);

            if ($target->role === $newRole) {
                throw ValidationException::withMessages([
                    'role' => 'The user already has this role.',
                ]);
            }

            $oldRole = $target->role;
            $this->capacity->assertCanTransition($shop, $target, $target->is_active, $newRole);

            $target->forceFill(['role' => $newRole])->save();

            $this->audit($shop, $lockedActor, $target, 'user.role_changed',
                ['role' => $oldRole], ['role' => $newRole], $reason);

            return $target->refresh();
        });
    }

    /**
     * Lock order is shop, actor, then target. All shop-user lifecycle mutations
     * use this order before checking authorization or capacity.
     *
     * @return array{Shop, User, User}
     */
    private function lockContext(User $actor, int $targetId): array
    {
        $shop = $this->capacity->lockActiveShop($actor->shop_id);
        $lockedActor = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();

        if (! $lockedActor->is_active || (string) $lockedActor->shop_id !== (string) $shop->getKey()) {
            abort(403, 'An active shop owner account is required.');
        }

        $target = User::query()
            ->whereKey($targetId)
            ->where('shop_id', $shop->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return [$shop, $lockedActor, $target];
    }

    private function audit(
        Shop $shop,
        User $actor,
        User $target,
        string $action,
        array $oldValues,
        array $newValues,
        ?string $reason,
    ): void {
        DB::table('user_audit_logs')->insert([
            'shop_id' => $shop->getKey(),
            'actor_user_id' => $actor->getKey(),
            'target_user_id' => $target->getKey(),
            'action' => $action,
            'old_values' => json_encode($oldValues, JSON_THROW_ON_ERROR),
            'new_values' => json_encode($newValues, JSON_THROW_ON_ERROR),
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
