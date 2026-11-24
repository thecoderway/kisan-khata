<?php

namespace App\\Modules\\Users\\Application;

use App\\Models\\Shop;
use App\\Models\\User;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Gate;
use Illuminate\\Validation\\ValidationException;

class CreateShopUser
{
    private const ROLE_LIMITS = [
        User::ROLE_OWNER => 1,
        User::ROLE_MANAGER => 2,
        User::ROLE_STAFF => 5,
    ];

    private const TOTAL_ACTIVE_USER_LIMIT = 8;

    /**
     * Create a Manager or Staff account for the authenticated Owner's shop.
     *
     * The shop row is locked before checking capacity. All services that change
     * active membership or a user's role must acquire the same lock first.
     *
     * @param array{name: string, email: string, mobile: string, role: string, temporary_password: string} $attributes
     */
    public function handle(User $actor, array $attributes): User
    {
        return DB::transaction(function () use ($actor, $attributes): User {
            $role = $attributes['role'];

            Gate::forUser($actor)->authorize('create', [User::class, $role]);

            $shop = Shop::query()
                ->whereKey($actor->shop_id)
                ->lockForUpdate()
                ->first();

            if (! $shop || $shop->status !== Shop::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'shop' => 'Users can only be added to an active shop.',
                ]);
            }

            $activeUsers = $shop->users()->where('is_active', true);

            if ((clone $activeUsers)->count() >= self::TOTAL_ACTIVE_USER_LIMIT) {
                throw ValidationException::withMessages([
                    'role' => 'This shop has reached its maximum of 8 active users.',
                ]);
            }

            $roleLimit = self::ROLE_LIMITS[$role] ?? 0;
            $activeRoleUsers = (clone $activeUsers)->where('role', $role)->count();

            if ($activeRoleUsers >= $roleLimit) {
                throw ValidationException::withMessages([
                    'role' => "This shop has reached its maximum of {$roleLimit} active {$role} users.",
                ]);
            }

            $user = new User;

            $user->forceFill([
                'shop_id' => $actor->shop_id,
                'name' => trim($attributes['name']),
                'email' => mb_strtolower(trim($attributes['email'])),
                'mobile' => trim($attributes['mobile']),
                'password' => $attributes['temporary_password'],
                'role' => $role,
                'preferred_language' => $actor->preferred_language ?: 'hi',
                'is_active' => true,
                'must_change_password' => true,
            ]);

            $user->save();

            return $user;
        });
    }
}
