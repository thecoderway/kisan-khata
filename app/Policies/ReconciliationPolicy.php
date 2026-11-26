<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class ReconciliationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->isOwner($actor);
    }

    public function viewCustomer(User $actor, Customer $customer): bool
    {
        return $this->isOwner($actor)
            && (string) $actor->shop_id === (string) $customer->shop_id;
    }

    public function repair(User $actor, Customer $customer): bool
    {
        return $this->viewCustomer($actor, $customer);
    }

    private function isOwner(User $actor): bool
    {
        return (bool) $actor->is_active
            && $actor->shop_id !== null
            && $actor->role === User::ROLE_OWNER;
    }
}
