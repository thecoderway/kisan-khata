<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->isActiveShopUser($actor);
    }

    public function view(User $actor, Customer $customer): bool
    {
        return $this->belongsToSameShop($actor, $customer);
    }

    public function viewDeleted(User $actor): bool
    {
        return $this->isActiveShopUser($actor)
            && in_array($actor->role, [User::ROLE_OWNER, User::ROLE_MANAGER], true);
    }

    public function create(User $actor): bool
    {
        return $this->isActiveShopUser($actor);
    }

    public function update(User $actor, Customer $customer): bool
    {
        return $this->belongsToSameShop($actor, $customer);
    }

    public function deactivate(User $actor, Customer $customer): bool
    {
        return $this->isLifecycleManager($actor, $customer);
    }

    public function reactivate(User $actor, Customer $customer): bool
    {
        return $this->isLifecycleManager($actor, $customer);
    }

    public function softDelete(User $actor, Customer $customer): bool
    {
        return $this->isLifecycleManager($actor, $customer);
    }

    public function restore(User $actor, Customer $customer): bool
    {
        return $this->isLifecycleManager($actor, $customer);
    }

    public function delete(User $actor, Customer $customer): bool
    {
        return false;
    }

    private function isLifecycleManager(User $actor, Customer $customer): bool
    {
        return $this->belongsToSameShop($actor, $customer)
            && in_array($actor->role, [User::ROLE_OWNER, User::ROLE_MANAGER], true);
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
