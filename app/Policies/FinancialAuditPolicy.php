<?php

namespace App\Policies;

use App\Models\User;

class FinancialAuditPolicy
{
    public function viewAny(User $actor): bool
    {
        return (bool) $actor->is_active
            && $actor->shop_id !== null
            && $actor->role === User::ROLE_OWNER;
    }
}
