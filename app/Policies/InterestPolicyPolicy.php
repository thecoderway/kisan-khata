<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class InterestPolicyPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->isActiveShopUser($actor);
    }

    public function viewCustomer(User $actor, Customer $customer): bool
    {
        return $this->belongsToSameShop($actor, $customer);
    }

    public function update(User $actor): bool
    {
        return $this->isOwner($actor);
    }

    public function updateCustomer(User $actor, Customer $customer): bool
    {
        return $this->isOwner($actor) && $this->belongsToSameShop($actor, $customer);
    }

    private function isOwner(User $actor): bool
    {
        return $this->isActiveShopUser($actor) && $actor->role === User::ROLE_OWNER;
    }

    private function isActiveShopUser(User $actor): bool
    {
        return (bool) $actor->is_active
            && $actor->shop_id !== null
            && in_array($actor->role, [User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_STAFF], true);
    }

    private function belongsToSameShop(User $actor, Customer $customer): bool
    {
        return $this->isActiveShopUser($actor)
            && (string) $actor->shop_id === (string) $customer->shop_id;
    }
}
