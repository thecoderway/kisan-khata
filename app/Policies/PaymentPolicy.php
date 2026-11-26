<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->isActiveShopUser($actor);
    }

    public function view(User $actor, Payment $payment): bool
    {
        return $this->belongsToSameShop($actor, $payment);
    }

    public function create(User $actor): bool
    {
        return $this->isActiveShopUser($actor);
    }

    public function correct(User $actor, Payment $payment): bool
    {
        return $actor->role === User::ROLE_OWNER && $this->belongsToSameShop($actor, $payment);
    }

    private function isActiveShopUser(User $actor): bool
    {
        return (bool) $actor->is_active
            && $actor->shop_id !== null
            && in_array($actor->role, [User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_STAFF], true);
    }

    private function belongsToSameShop(User $actor, Payment $payment): bool
    {
        return $this->isActiveShopUser($actor)
            && (string) $actor->shop_id === (string) $payment->shop_id;
    }
}
