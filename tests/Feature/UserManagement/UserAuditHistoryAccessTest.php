<?php

namespace Tests\Feature\UserManagement;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserAuditHistoryAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_only_their_shop_audit_history(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $ownerA = $this->createUser($shopA, User::ROLE_OWNER);
        $actorA = $this->createUser($shopA, User::ROLE_MANAGER);
        $targetA = $this->createUser($shopA, User::ROLE_STAFF);
        $ownerB = $this->createUser($shopB, User::ROLE_OWNER);
        $targetB = $this->createUser($shopB, User::ROLE_STAFF);

        $this->createAudit($shopA, $actorA, $targetA, 'user.deactivated');
        $this->createAudit($shopA, $ownerA, $targetA, 'user.reactivated');
        $this->createAudit($shopB, $ownerB, $targetB, 'user.deactivated');

        Sanctum::actingAs($ownerA, ['app']);

        $this->getJson('/api/shop/users/audit-logs?per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('data.0.shop_id', $shopA->id)
            ->assertJsonPath('data.0.action', 'user.reactivated')
            ->assertJsonPath('data.0.actor.id', $ownerA->id)
            ->assertJsonPath('data.0.target.id', $targetA->id)
            ->assertJsonMissing(['shop_id' => $shopB->id]);
    }

    public function test_manager_cannot_view_user_audit_history(): void
    {
        $shop = Shop::factory()->create();
        $manager = $this->createUser($shop, User::ROLE_MANAGER);

        Sanctum::actingAs($manager, ['app']);

        $this->getJson('/api/shop/users/audit-logs')->assertForbidden();
    }

    public function test_staff_cannot_view_user_audit_history(): void
    {
        $shop = Shop::factory()->create();
        $staff = $this->createUser($shop, User::ROLE_STAFF);

        Sanctum::actingAs($staff, ['app']);

        $this->getJson('/api/shop/users/audit-logs')->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_view_user_audit_history(): void
    {
        $this->getJson('/api/shop/users/audit-logs')->assertUnauthorized();
    }

    public function test_audit_history_rejects_invalid_page_size(): void
    {
        $shop = Shop::factory()->create();
        $owner = $this->createUser($shop, User::ROLE_OWNER);

        Sanctum::actingAs($owner, ['app']);

        $this->getJson('/api/shop/users/audit-logs?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    private function createUser(Shop $shop, string $role): User
    {
        return User::factory()->create([
            'shop_id' => $shop->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function createAudit(Shop $shop, User $actor, User $target, string $action): void
    {
        DB::table('user_audit_logs')->insert([
            'shop_id' => $shop->id,
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'action' => $action,
            'old_values' => json_encode(['is_active' => true], JSON_THROW_ON_ERROR),
            'new_values' => json_encode(['is_active' => false], JSON_THROW_ON_ERROR),
            'reason' => null,
            'created_at' => now(),
        ]);
    }
}
