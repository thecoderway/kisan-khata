<?php

namespace Tests\Feature\UserManagement;

use App\Models\Shop;
use App\Models\User;
use App\Modules\Users\Application\CreateShopUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateShopUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_manager_with_temporary_password_for_their_shop(): void
    {
        $shop = Shop::factory()->create();
        $owner = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
            'preferred_language' => 'hi',
        ]);

        $user = app(CreateShopUser::class)->handle($owner, [
            'name' => '  Suresh Kumar  ',
            'email' => 'SURESH@example.com',
            'mobile' => '9876543211',
            'role' => User::ROLE_MANAGER,
            'temporary_password' => 'TempPass123',
        ]);

        $this->assertSame($shop->id, $user->shop_id);
        $this->assertSame('Suresh Kumar', $user->name);
        $this->assertSame('suresh@example.com', $user->email);
        $this->assertSame(User::ROLE_MANAGER, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check('TempPass123', $user->password));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'shop_id' => $shop->id,
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
            'must_change_password' => true,
        ]);
    }

    public function test_owner_can_create_staff_and_client_shop_id_is_ignored(): void
    {
        $shop = Shop::factory()->create();
        $otherShop = Shop::factory()->create();
        $owner = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $user = app(CreateShopUser::class)->handle($owner, [
            'name' => 'Staff Member',
            'email' => 'staff@example.com',
            'mobile' => '9876543212',
            'role' => User::ROLE_STAFF,
            'temporary_password' => 'TempPass123',
            'shop_id' => $otherShop->id,
        ]);

        $this->assertSame($shop->id, $user->shop_id);
        $this->assertNotSame($otherShop->id, $user->shop_id);
        $this->assertSame(User::ROLE_STAFF, $user->role);
    }

    public function test_manager_cannot_create_shop_users(): void
    {
        $shop = Shop::factory()->create();
        $manager = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        $this->expectException(AuthorizationException::class);

        app(CreateShopUser::class)->handle($manager, [
            'name' => 'Staff Member',
            'email' => 'staff@example.com',
            'mobile' => '9876543213',
            'role' => User::ROLE_STAFF,
            'temporary_password' => 'TempPass123',
        ]);
    }

    public function test_platform_owner_cannot_create_shop_users_through_shop_management(): void
    {
        $platformOwner = User::factory()->create([
            'shop_id' => null,
            'role' => User::ROLE_PLATFORM_OWNER,
            'is_active' => true,
        ]);

        $this->expectException(AuthorizationException::class);

        app(CreateShopUser::class)->handle($platformOwner, [
            'name' => 'Staff Member',
            'email' => 'staff@example.com',
            'mobile' => '9876543214',
            'role' => User::ROLE_STAFF,
            'temporary_password' => 'TempPass123',
        ]);
    }

    public function test_inactive_owner_cannot_create_shop_users(): void
    {
        $shop = Shop::factory()->create();
        $owner = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => false,
        ]);

        $this->expectException(AuthorizationException::class);

        app(CreateShopUser::class)->handle($owner, [
            'name' => 'Staff Member',
            'email' => 'staff@example.com',
            'mobile' => '9876543215',
            'role' => User::ROLE_STAFF,
            'temporary_password' => 'TempPass123',
        ]);
    }

    public function test_owner_cannot_create_a_third_active_manager(): void
    {
        $shop = Shop::factory()->create();
        $owner = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        User::factory()->count(2)->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        $this->expectException(\\Illuminate\\Validation\\ValidationException::class);

        app(CreateShopUser::class)->handle($owner, [
            'name' => 'Third Manager',
            'email' => 'third.manager@example.com',
            'mobile' => '9876543216',
            'role' => User::ROLE_MANAGER,
            'temporary_password' => 'TempPass123',
        ]);
    }

    public function test_owner_cannot_create_a_sixth_active_staff_member(): void
    {
        $shop = Shop::factory()->create();
        $owner = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        User::factory()->count(5)->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        $this->expectException(\\Illuminate\\Validation\\ValidationException::class);

        app(CreateShopUser::class)->handle($owner, [
            'name' => 'Sixth Staff',
            'email' => 'sixth.staff@example.com',
            'mobile' => '9876543217',
            'role' => User::ROLE_STAFF,
            'temporary_password' => 'TempPass123',
        ]);
    }

    public function test_deactivated_users_do_not_count_toward_role_capacity(): void
    {
        $shop = Shop::factory()->create();
        $owner = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_MANAGER,
            'is_active' => false,
        ]);

        $user = app(CreateShopUser::class)->handle($owner, [
            'name' => 'Replacement Manager',
            'email' => 'replacement.manager@example.com',
            'mobile' => '9876543218',
            'role' => User::ROLE_MANAGER,
            'temporary_password' => 'TempPass123',
        ]);

        $this->assertSame(User::ROLE_MANAGER, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertSame(2, $shop->users()->where('role', User::ROLE_MANAGER)->where('is_active', true)->count());
    }

}
