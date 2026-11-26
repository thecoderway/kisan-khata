<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->isActiveShopUser($actor);
    }

    public function view(User $actor, Sale $sale): bool
    {
        return $this->belongsToSameShop($actor, $sale);
    }

    public function create(User $actor): bool
    {
        return $this->isActiveShopUser($actor);
    }

    public function revise(User $actor, Sale $sale): bool
    {
        return $actor->role === User::ROLE_OWNER && $this->belongsToSameShop($actor, $sale);
    }

    private function isActiveShopUser(User $actor): bool
    {
        return (bool) $actor->is_active
            && $actor->shop_id !== null
            && in_array($actor->role, [User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_STAFF], true);
    }

    private function belongsToSameShop(User $actor, Sale $sale): bool
    {
        return $this->isActiveShopUser($actor)
            && (string) $actor->shop_id === (string) $sale->shop_id;
    }
}
