<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\UserManagementPolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserManagementPolicyTest extends TestCase
{
    private UserManagementPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new UserManagementPolicy;
    }

    #[Test]
    public function owner_and_manager_can_open_user_list_but_staff_and_platform_users_cannot(): void
    {
        $this->assertTrue($this->policy->viewAny($this->user(User::ROLE_OWNER)));
        $this->assertTrue($this->policy->viewAny($this->user(User::ROLE_MANAGER)));
        $this->assertFalse($this->policy->viewAny($this->user(User::ROLE_STAFF)));
        $this->assertFalse($this->policy->viewAny($this->user(User::ROLE_PLATFORM_OWNER, null)));
    }

    #[Test]
    public function manager_can_view_staff_accounts_only(): void
    {
        $manager = $this->user(User::ROLE_MANAGER);

        $this->assertTrue($this->policy->view($manager, $this->user(User::ROLE_STAFF)));
        $this->assertFalse($this->policy->view($manager, $this->user(User::ROLE_MANAGER)));
        $this->assertFalse($this->policy->view($manager, $this->user(User::ROLE_OWNER)));
    }

    #[Test]
    public function cross_shop_accounts_are_never_visible(): void
    {
        $owner = $this->user(User::ROLE_OWNER, 10);
        $otherShopStaff = $this->user(User::ROLE_STAFF, 20);

        $this->assertFalse($this->policy->view($owner, $otherShopStaff));
        $this->assertFalse($this->policy->viewStatus($owner, $otherShopStaff));
    }

    #[Test]
    public function only_owner_can_create_manager_or_staff_accounts(): void
    {
        $owner = $this->user(User::ROLE_OWNER);

        $this->assertTrue($this->policy->create($owner, User::ROLE_MANAGER));
        $this->assertTrue($this->policy->create($owner, User::ROLE_STAFF));
        $this->assertFalse($this->policy->create($owner, User::ROLE_OWNER));
        $this->assertFalse($this->policy->create($owner, User::ROLE_PLATFORM_OWNER));
        $this->assertFalse($this->policy->create($this->user(User::ROLE_MANAGER), User::ROLE_STAFF));
        $this->assertFalse($this->policy->create($this->user(User::ROLE_STAFF), User::ROLE_STAFF));
    }

    #[Test]
    public function only_owner_can_deactivate_or_reactivate_other_manager_and_staff_accounts(): void
    {
        $owner = $this->user(User::ROLE_OWNER);
        $manager = $this->user(User::ROLE_MANAGER);
        $staff = $this->user(User::ROLE_STAFF);

        $this->assertTrue($this->policy->deactivate($owner, $manager));
        $this->assertTrue($this->policy->deactivate($owner, $staff));
        $this->assertTrue($this->policy->reactivate($owner, $staff));
        $this->assertFalse($this->policy->deactivate($manager, $staff));
        $this->assertFalse($this->policy->reactivate($staff, $manager));
    }

    #[Test]
    public function self_owner_and_platform_accounts_cannot_be_administered_through_ordinary_management(): void
    {
        $owner = $this->user(User::ROLE_OWNER);
        $otherOwner = $this->user(User::ROLE_OWNER, 11);
        $platformOwner = $this->user(User::ROLE_PLATFORM_OWNER, null);

        $this->assertFalse($this->policy->deactivate($owner, $owner));
        $this->assertFalse($this->policy->deactivate($owner, $otherOwner));
        $this->assertFalse($this->policy->reactivate($owner, $platformOwner));
        $this->assertFalse($this->policy->changeRole($owner, $owner, User::ROLE_STAFF));
    }

    #[Test]
    public function only_owner_can_change_manager_or_staff_between_shop_roles(): void
    {
        $owner = $this->user(User::ROLE_OWNER);
        $manager = $this->user(User::ROLE_MANAGER);
        $staff = $this->user(User::ROLE_STAFF);

        $this->assertTrue($this->policy->changeRole($owner, $manager, User::ROLE_STAFF));
        $this->assertTrue($this->policy->changeRole($owner, $staff, User::ROLE_MANAGER));
        $this->assertFalse($this->policy->changeRole($owner, $manager, User::ROLE_OWNER));
        $this->assertFalse($this->policy->changeRole($owner, $manager, User::ROLE_PLATFORM_OWNER));
        $this->assertFalse($this->policy->changeRole($manager, $staff, User::ROLE_MANAGER));
    }

    #[Test]
    public function inactive_actor_cannot_use_user_management_permissions(): void
    {
        $inactiveOwner = $this->user(User::ROLE_OWNER, 10, false);
        $staff = $this->user(User::ROLE_STAFF);

        $this->assertFalse($this->policy->viewAny($inactiveOwner));
        $this->assertFalse($this->policy->create($inactiveOwner, User::ROLE_STAFF));
        $this->assertFalse($this->policy->deactivate($inactiveOwner, $staff));
    }

    private function user(string $role, ?int $shopId = 10, bool $active = true): User
    {
        static $nextId = 1;

        $user = new User;
        $user->forceFill([
            'id' => $nextId++,
            'shop_id' => $shopId,
            'role' => $role,
            'is_active' => $active,
        ]);

        return $user;
    }
}
