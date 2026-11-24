<?php

namespace App\\Policies;

use App\\Models\\User;

class UserManagementPolicy
{
    /**
     * An Owner can manage shop users. A Manager has read-only visibility
     * into the Staff list and their active/deactivated status.
     */
    public function viewAny(User $actor): bool
    {
        return $this->isActiveShopUser($actor)
            && in_array($actor->role, [User::ROLE_OWNER, User::ROLE_MANAGER], true);
    }

    /**
     * Owners can view users in their own shop. Managers can view Staff only.
     */
    public function view(User $actor, User $target): bool
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

    /**
     * Only the Shop Owner may create Manager or Staff accounts.
     */
    public function create(User $actor, string $role): bool
    {
        return $this->isShopOwner($actor)
            && in_array($role, [User::ROLE_MANAGER, User::ROLE_STAFF], true);
    }

    /**
     * Only the Shop Owner may deactivate another Manager or Staff account.
     */
    public function deactivate(User $actor, User $target): bool
    {
        return $this->canAdministerTarget($actor, $target);
    }

    /**
     * Only the Shop Owner may reactivate a Manager or Staff account.
     */
    public function reactivate(User $actor, User $target): bool
    {
        return $this->canAdministerTarget($actor, $target);
    }

    /**
     * Only the Shop Owner may change a Manager's or Staff member's role.
     */
    public function changeRole(User $actor, User $target, string $newRole): bool
    {
        return $this->canAdministerTarget($actor, $target)
            && in_array($newRole, [User::ROLE_MANAGER, User::ROLE_STAFF], true);
    }

    /**
     * Managers may see Staff status only; Owners may see status within their shop.
     */
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
