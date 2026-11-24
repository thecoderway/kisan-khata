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
            // Keep the shared lock order used by lifecycle mutations: shop, actor.
            // The authenticated model may be stale, so authorize only from the
            // freshly loaded and locked database row.
            $shop = $this->capacity->lockActiveShop($actor->shop_id);
            $lockedActor = User::query()
                ->whereKey($actor->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedActor->is_active
                || (string) $lockedActor->shop_id !== (string) $shop->getKey()) {
                abort(403, 'An active shop owner account is required.');
            }

            $role = $attributes['role'];
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
