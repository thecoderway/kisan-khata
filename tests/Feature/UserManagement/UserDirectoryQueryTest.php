<?php

namespace Tests\Feature\UserManagement;

use App\Models\Shop;
use App\Models\User;
use App\Modules\Users\Application\UserDirectoryQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDirectoryQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_permitted_user_account_details_for_their_shop(): void
    {
        $shop = Shop::factory()->create();

        $owner = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_STAFF,
            'name' => 'Test Staff',
            'email' => 'staff@example.com',
            'mobile' => '9876543210',
            'is_active' => true,
        ]);

        $users = app(UserDirectoryQuery::class)->forActor($owner, Request::create('/users'));

        $this->assertTrue($users->getCollection()->contains('id', $owner->id));
        $this->assertTrue($users->getCollection()->contains('id', $staff->id));

        $staffRecord = $users->getCollection()->firstWhere('id', $staff->id);

        $this->assertSame('staff@example.com', $staffRecord->email);
        $this->assertSame('9876543210', $staffRecord->mobile);
        $this->assertSame(User::ROLE_STAFF, $staffRecord->role);
    }

    public function test_owner_directory_does_not_include_users_from_another_shop(): void
    {
        $shop = Shop::factory()->create();
        $otherShop = Shop::factory()->create();

        $owner = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $otherUser = User::factory()->create([
            'shop_id' => $otherShop->id,
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        $users = app(UserDirectoryQuery::class)->forActor($owner);

        $this->assertFalse($users->getCollection()->contains('id', $otherUser->id));
    }

    public function test_manager_receives_only_staff_with_minimal_fields(): void
    {
        $shop = Shop::factory()->create();

        $manager = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        $users = app(UserDirectoryQuery::class)->forActor($manager, Request::create('/users'));

        $this->assertSame(1, $users->total());
        $this->assertSame($staff->id, $users->getCollection()->first()->id);

        $this->assertSame(
            ['id', 'name', 'role', 'is_active'],
            array_keys($users->getCollection()->first()->getAttributes()),
        );
    }

    public function test_manager_cannot_view_staff_from_another_shop(): void
    {
        $shop = Shop::factory()->create();
        $otherShop = Shop::factory()->create();

        $manager = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        $otherStaff = User::factory()->create([
            'shop_id' => $otherShop->id,
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        $users = app(UserDirectoryQuery::class)->forActor($manager);

        $this->assertFalse($users->getCollection()->contains('id', $otherStaff->id));
    }

    public function test_staff_cannot_access_user_directory(): void
    {
        $shop = Shop::factory()->create();

        $staff = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        $this->expectException(AuthorizationException::class);

        app(UserDirectoryQuery::class)->forActor($staff, Request::create('/users'));
    }

    public function test_platform_owner_cannot_access_shop_user_directory(): void
    {
        $platformOwner = User::factory()->create([
            'shop_id' => null,
            'role' => User::ROLE_PLATFORM_OWNER,
            'is_active' => true,
        ]);

        $this->expectException(AuthorizationException::class);

        app(UserDirectoryQuery::class)->forActor($platformOwner, Request::create('/users'));
    }
}
