<?php

namespace Tests\Feature\UserManagement;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_shop_user_directory_and_capacity_summary(): void
    {
        $shop = Shop::factory()->create();
        $owner = $this->userFor($shop, User::ROLE_OWNER);
        $staff = $this->userFor($shop, User::ROLE_STAFF, [
            'name' => 'Directory Staff',
            'email' => 'directory.staff@example.com',
        ]);

        $this->actingAs($owner)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Directory Staff')
            ->assertSee('directory.staff@example.com')
            ->assertSee('सक्रिय खाते')
            ->assertViewHas('capacity', fn (array $capacity): bool => $capacity['active_total'] === 2);
    }

    public function test_manager_sees_only_staff_name_and_status_in_web_directory(): void
    {
        $shop = Shop::factory()->create();
        $manager = $this->userFor($shop, User::ROLE_MANAGER);
        $staff = $this->userFor($shop, User::ROLE_STAFF, [
            'name' => 'Visible Staff',
            'email' => 'private.staff@example.com',
            'mobile' => '9876543210',
        ]);

        $this->actingAs($manager)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Visible Staff')
            ->assertDontSee('private.staff@example.com')
            ->assertDontSee('9876543210')
            ->assertDontSee('नया यूज़र')
            ->assertViewHas('capacity', null);
    }

    public function test_staff_cannot_open_user_management_web_pages(): void
    {
        $shop = Shop::factory()->create();
        $staff = $this->userFor($shop, User::ROLE_STAFF);

        $this->actingAs($staff)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_owner_can_create_manager_and_creation_is_audited(): void
    {
        $shop = Shop::factory()->create();
        $owner = $this->userFor($shop, User::ROLE_OWNER);

        $this->actingAs($owner)
            ->post(route('users.store'), [
                'name' => 'New Manager',
                'email' => 'new.manager@example.com',
                'mobile' => '9876543219',
                'role' => User::ROLE_MANAGER,
                'temporary_password' => 'TempPass123',
                'temporary_password_confirmation' => 'TempPass123',
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHasNoErrors();

        $created = User::query()->where('email', 'new.manager@example.com')->firstOrFail();

        $this->assertSame($shop->id, $created->shop_id);
        $this->assertTrue($created->must_change_password);

        $this->assertDatabaseHas('user_audit_logs', [
            'shop_id' => $shop->id,
            'actor_user_id' => $owner->id,
            'target_user_id' => $created->id,
            'action' => 'user.created',
        ]);
    }

    public function test_manager_cannot_open_user_creation_page_or_submit_creation(): void
    {
        $shop = Shop::factory()->create();
        $manager = $this->userFor($shop, User::ROLE_MANAGER);

        $this->actingAs($manager)
            ->get(route('users.create'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('users.store'), [
                'name' => 'Blocked Staff',
                'email' => 'blocked.staff@example.com',
                'mobile' => '9876543220',
                'role' => User::ROLE_STAFF,
                'temporary_password' => 'TempPass123',
                'temporary_password_confirmation' => 'TempPass123',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'blocked.staff@example.com']);
    }

    public function test_only_owner_can_open_user_audit_history(): void
    {
        $shop = Shop::factory()->create();
        $owner = $this->userFor($shop, User::ROLE_OWNER);
        $manager = $this->userFor($shop, User::ROLE_MANAGER);

        $this->actingAs($owner)
            ->get(route('users.audit'))
            ->assertOk()
            ->assertSee('यूज़र ऑडिट हिस्ट्री');

        $this->actingAs($manager)
            ->get(route('users.audit'))
            ->assertForbidden();
    }

    public function test_user_directory_is_scoped_to_the_authenticated_shop(): void
    {
        $shop = Shop::factory()->create();
        $otherShop = Shop::factory()->create();
        $owner = $this->userFor($shop, User::ROLE_OWNER);
        $otherStaff = $this->userFor($otherShop, User::ROLE_STAFF, [
            'name' => 'Other Shop Private User',
            'email' => 'other.shop@example.com',
        ]);

        $this->actingAs($owner)
            ->get(route('users.index', ['shop_id' => $otherShop->id]))
            ->assertOk()
            ->assertDontSee('Other Shop Private User')
            ->assertDontSee('other.shop@example.com');
    }

    public function test_owner_can_manage_user_lifecycle_through_web_routes(): void
    {
        $shop = Shop::factory()->create();
        $owner = $this->userFor($shop, User::ROLE_OWNER);
        $staff = $this->userFor($shop, User::ROLE_STAFF);

        config(['session.driver' => 'database']);

        $this->actingAs($owner)
            ->from(route('users.index'))
            ->post(route('users.deactivate', $staff->id), ['reason' => 'Role change'])
            ->assertRedirect(route('users.index'));

        $this->assertFalse($staff->fresh()->is_active);
        $this->assertDatabaseHas('user_audit_logs', [
            'actor_user_id' => $owner->id,
            'target_user_id' => $staff->id,
            'action' => 'user.deactivated',
        ]);

        $this->actingAs($owner)
            ->from(route('users.index'))
            ->post(route('users.reactivate', $staff->id))
            ->assertRedirect(route('users.index'));

        $this->assertTrue($staff->fresh()->is_active);

        $this->actingAs($owner)
            ->from(route('users.index'))
            ->patch(route('users.role', $staff->id), ['role' => User::ROLE_MANAGER])
            ->assertRedirect(route('users.index'));

        $this->assertSame(User::ROLE_MANAGER, $staff->fresh()->role);
        $this->assertDatabaseHas('user_audit_logs', [
            'actor_user_id' => $owner->id,
            'target_user_id' => $staff->id,
            'action' => 'user.role_changed',
        ]);
    }

    public function test_manager_cannot_submit_user_lifecycle_web_actions(): void
    {
        $shop = Shop::factory()->create();
        $manager = $this->userFor($shop, User::ROLE_MANAGER);
        $staff = $this->userFor($shop, User::ROLE_STAFF);

        $this->actingAs($manager)
            ->post(route('users.deactivate', $staff->id))
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('users.reactivate', $staff->id))
            ->assertForbidden();

        $this->actingAs($manager)
            ->patch(route('users.role', $staff->id), ['role' => User::ROLE_MANAGER])
            ->assertForbidden();

        $this->assertTrue($staff->fresh()->is_active);
        $this->assertSame(User::ROLE_STAFF, $staff->fresh()->role);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userFor(Shop $shop, string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'shop_id' => $shop->id,
            'role' => $role,
            'is_active' => true,
        ], $attributes));
    }
}
