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
     * @param array{name: string, email: string, mobile: string, role: string, temporary_password: string} $attributes
     */
    public function handle(User $actor, array $attributes): User
    {
        return DB::transaction(function () use ($actor, $attributes): User {
            $role = $attributes['role'];

            Gate::forUser($actor)->authorize('create', [User::class, $role]);

            // Lock and validate the shop before checking capacity. Every future
            // lifecycle/role service must use this same lock before mutations.
            $shop = $this->capacity->lockActiveShop($actor->shop_id);
            $this->capacity->assertCanAdd($shop, $role);

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
