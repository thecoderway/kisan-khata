<?php

namespace Tests\Feature\Api;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShopUserLifecycleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_deactivate_a_shop_user_and_api_response_excludes_private_fields(): void
    {
        $shop = Shop::factory()->create();
        $owner = $this->userFor($shop, User::ROLE_OWNER);
        $staff = $this->userFor($shop, User::ROLE_STAFF, [
            'email' => 'private.staff@example.com',
            'mobile' => '9876543210',
        ]);

        config(['session.driver' => 'database']);

        $staff->createToken('mobile-device', ['app']);
        DB::table('sessions')->insert([
            'id' => 'staff-session',
            'user_id' => $staff->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test-agent',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);

        Sanctum::actingAs($owner, ['app']);

        $this->postJson("/api/shop/users/{$staff->id}/deactivate", [
            'reason' => 'Leaving the shop',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $staff->id)
            ->assertJsonPath('user.is_active', false)
            ->assertJsonMissingPath('user.email')
            ->assertJsonMissingPath('user.mobile')
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token')
            ->assertJsonMissingPath('user.shop_id');

        $this->assertFalse($staff->fresh()->is_active);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $staff->id,
        ]);
        $this->assertDatabaseMissing('sessions', ['id' => 'staff-session']);
        $this->assertDatabaseHas('user_audit_logs', [
            'shop_id' => $shop->id,
            'actor_user_id' => $owner->id,
            'target_user_id' => $staff->id,
            'action' => 'user.deactivated',
        ]);
    }

    public function test_owner_can_reactivate_and_change_role_through_api(): void
    {
        $shop = Shop::factory()->create();
        $owner = $this->userFor($shop, User::ROLE_OWNER);
        $staff = $this->userFor($shop, User::ROLE_STAFF, ['is_active' => false]);

        Sanctum::actingAs($owner, ['app']);

        $this->postJson("/api/shop/users/{$staff->id}/reactivate")
            ->assertOk()
            ->assertJsonPath('user.is_active', true);

        $this->patchJson("/api/shop/users/{$staff->id}/role", [
            'role' => User::ROLE_MANAGER,
        ])
            ->assertOk()
            ->assertJsonPath('user.role', User::ROLE_MANAGER);

        $this->assertDatabaseHas('user_audit_logs', [
            'target_user_id' => $staff->id,
            'action' => 'user.reactivated',
        ]);
        $this->assertDatabaseHas('user_audit_logs', [
            'target_user_id' => $staff->id,
            'action' => 'user.role_changed',
        ]);
    }

    public function test_manager_and_staff_cannot_manage_shop_users_through_api(): void
    {
        $shop = Shop::factory()->create();
        $manager = $this->userFor($shop, User::ROLE_MANAGER);
        $staffActor = $this->userFor($shop, User::ROLE_STAFF);
        $target = $this->userFor($shop, User::ROLE_STAFF);

        Sanctum::actingAs($manager, ['app']);
        $this->postJson("/api/shop/users/{$target->id}/deactivate")->assertForbidden();

        Sanctum::actingAs($staffActor, ['app']);
        $this->patchJson("/api/shop/users/{$target->id}/role", [
            'role' => User::ROLE_MANAGER,
        ])->assertForbidden();

        $this->assertTrue($target->fresh()->is_active);
        $this->assertSame(User::ROLE_STAFF, $target->fresh()->role);
    }

    public function test_owner_cannot_manage_another_shops_user_through_api(): void
    {
        $shop = Shop::factory()->create();
        $otherShop = Shop::factory()->create();
        $owner = $this->userFor($shop, User::ROLE_OWNER);
        $otherStaff = $this->userFor($otherShop, User::ROLE_STAFF);

        Sanctum::actingAs($owner, ['app']);

        $this->postJson("/api/shop/users/{$otherStaff->id}/deactivate")
            ->assertNotFound();

        $this->assertTrue($otherStaff->fresh()->is_active);
    }

    public function test_unauthenticated_user_cannot_manage_shop_users_through_api(): void
    {
        $this->postJson('/api/shop/users/1/deactivate')->assertUnauthorized();
        $this->postJson('/api/shop/users/1/reactivate')->assertUnauthorized();
        $this->patchJson('/api/shop/users/1/role', ['role' => User::ROLE_STAFF])->assertUnauthorized();
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
