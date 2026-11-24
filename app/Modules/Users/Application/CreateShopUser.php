<?php

namespace App\Modules\Users\Application;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateShopUser
{
    public function __construct(
        private readonly ShopUserCapacity $capacity,
    ) {}

    /**
     * Create a Manager or Staff account for the authenticated Owner's shop.
     *
     * @param  array{name: string, email: string, mobile: string, role: string, temporary_password: string}  $attributes
     */
    public function handle(User $actor, array $attributes): User
    {
        return DB::transaction(function () use ($actor, $attributes): User {
            $role = $attributes['role'];

            // Resolve and pre-authorize the current database identity first.
            // This rejects platform owners and inactive/non-owner accounts
            // before attempting to lock a shop that may not exist for them.
            $currentActor = User::query()
                ->whereKey($actor->getKey())
                ->firstOrFail();

            Gate::forUser($currentActor)->authorize('create', [User::class, $role]);

            // Preserve the shared mutation lock order: shop, then actor.
            $shop = $this->capacity->lockActiveShop($currentActor->shop_id);
            $lockedActor = User::query()
                ->whereKey($currentActor->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Re-check authorization against the locked row to close the
            // race between the initial identity read and acquiring locks.
            if ((string) $lockedActor->shop_id !== (string) $shop->getKey()) {
                Gate::forUser($lockedActor)->authorize('create', [User::class, $role]);
                abort(403, 'The user is not authorized to manage this shop.');
            }

            Gate::forUser($lockedActor)->authorize('create', [User::class, $role]);

            $this->capacity->assertCanAdd($shop, $role);

            $user = new User;

            $user->forceFill([
                'shop_id' => $shop->getKey(),
                'name' => trim($attributes['name']),
                'email' => mb_strtolower(trim($attributes['email'])),
                'mobile' => trim($attributes['mobile']),
                'password' => $attributes['temporary_password'],
                'role' => $role,
                'preferred_language' => $lockedActor->preferred_language ?: 'hi',
                'is_active' => true,
                'must_change_password' => true,
            ]);

            $user->save();

            DB::table('user_audit_logs')->insert([
                'shop_id' => $shop->getKey(),
                'actor_user_id' => $lockedActor->getKey(),
                'target_user_id' => $user->getKey(),
                'action' => 'user.created',
                'old_values' => null,
                'new_values' => json_encode([
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                ], JSON_THROW_ON_ERROR),
                'reason' => null,
                'created_at' => now(),
            ]);

            return $user;
        });
    }
}
