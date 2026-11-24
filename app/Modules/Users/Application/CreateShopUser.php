<?php

namespace App\Modules\Users\Application;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateShopUser
{
    /**
     * Create a Manager or Staff account for the authenticated Owner's shop.
     *
     * The caller must pass validated input. Capacity enforcement is deliberately
     * handled by the next Phase 3 increment and this service is not exposed by a
     * route until that invariant is implemented.
     *
     * @param array{name: string, email: string, mobile: string, role: string, temporary_password: string} $attributes
     */
    public function handle(User $actor, array $attributes): User
    {
        $role = $attributes['role'];

        Gate::forUser($actor)->authorize('create', [User::class, $role]);

        return DB::transaction(function () use ($actor, $attributes, $role): User {
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
