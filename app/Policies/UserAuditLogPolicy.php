<?php

namespace App\Policies;

use App\Models\User;

class UserAuditLogPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->is_active
            && $actor->shop_id !== null
            && $actor->role === User::ROLE_OWNER;
    }
}
