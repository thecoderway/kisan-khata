<?php

namespace App\Policies;

use App\Models\User;

class UserManagementPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->isActiveShopUser($actor)
            && in_array($actor->role, [User::ROLE_OWNER, User::ROLE_MANAGER], true);
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->role === User::ROLE_OWNER
            && $this->belongsToSameShop($actor, $target);
    }

    public function create(User $actor, string $role): bool
    {
        return $this->isShopOwner($actor)
            && in_array($role, [User::ROLE_MANAGER, User::ROLE_STAFF], true);
    }

    public function deactivate(User $actor, User $target): bool
    {
        return $this->canAdministerTarget($actor, $target);
    }

    public function reactivate(User $actor, User $target): bool
    {
        return $this->canAdministerTarget($actor, $target);
    }

    public function changeRole(User $actor, User $target, string $newRole): bool
    {
        return $this->canAdministerTarget($actor, $target)
            && in_array($newRole, [User::ROLE_MANAGER, User::ROLE_STAFF], true);
    }

    public function viewStatus(User $actor, User $target): bool
    {
        if (! $this->belongsToSameShop($actor, $target)) {
            return false;
        }

        if ($actor->role === User::ROLE_OWNER) {
            return true;
        }

        return $actor->role === User::ROLE_MANAGER
            && $target->role === User::ROLE_STAFF;
    }

    private function canAdministerTarget(User $actor, User $target): bool
    {
        return $this->isShopOwner($actor)
            && $actor->isNot($target)
            && $this->belongsToSameShop($actor, $target)
            && in_array($target->role, [User::ROLE_MANAGER, User::ROLE_STAFF], true);
    }

    private function isShopOwner(User $user): bool
    {
        return $this->isActiveShopUser($user)
            && $user->role === User::ROLE_OWNER;
    }

    private function isActiveShopUser(User $user): bool
    {
        return (bool) $user->is_active && $user->shop_id !== null;
    }

    private function belongsToSameShop(User $actor, User $target): bool
    {
        return $this->isActiveShopUser($actor)
            && $target->shop_id !== null
            && (string) $actor->shop_id === (string) $target->shop_id;
    }
}
