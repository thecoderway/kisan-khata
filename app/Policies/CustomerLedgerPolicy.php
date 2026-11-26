<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerLedgerPolicy
{
    public function view(User $actor, Customer $customer): bool
    {
        return (bool) $actor->is_active
            && $actor->shop_id !== null
            && in_array($actor->role, [User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_STAFF], true)
            && (string) $actor->shop_id === (string) $customer->shop_id;
    }
}
