<?php

namespace Tests\Feature\UserManagement;

use App\Models\Shop;
use App\Models\User;
use App\Models\UserAuditLog;
use App\Tenancy\CurrentShop;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserAuditLogTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_model_queries_are_scoped_to_current_shop_and_fail_closed(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $ownerA = User::factory()->create(['shop_id' => $shopA->id, 'role' => User::ROLE_OWNER]);
        $targetA = User::factory()->create(['shop_id' => $shopA->id, 'role' => User::ROLE_STAFF]);
        $ownerB = User::factory()->create(['shop_id' => $shopB->id, 'role' => User::ROLE_OWNER]);
        $targetB = User::factory()->create(['shop_id' => $shopB->id, 'role' => User::ROLE_STAFF]);

        foreach ([[$shopA, $ownerA, $targetA], [$shopB, $ownerB, $targetB]] as [$shop, $actor, $target]) {
            DB::table('user_audit_logs')->insert([
                'shop_id' => $shop->id,
                'actor_user_id' => $actor->id,
                'target_user_id' => $target->id,
                'action' => 'user.deactivated',
                'old_values' => json_encode(['is_active' => true], JSON_THROW_ON_ERROR),
                'new_values' => json_encode(['is_active' => false], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        }

        $currentShop = app(CurrentShop::class);
        $currentShop->set($shopA);

        $this->assertSame([$shopA->id], UserAuditLog::query()->pluck('shop_id')->unique()->all());

        $currentShop->clear();

        $this->assertSame(0, UserAuditLog::query()->count());
    }

    public function test_audit_log_model_rejects_another_shops_insert(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $actor = User::factory()->create(['shop_id' => $shopB->id, 'role' => User::ROLE_OWNER]);
        $target = User::factory()->create(['shop_id' => $shopB->id, 'role' => User::ROLE_STAFF]);

        app(CurrentShop::class)->set($shopA);

        $this->expectException(AuthorizationException::class);

        UserAuditLog::create([
            'shop_id' => $shopB->id,
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'action' => 'user.deactivated',
            'created_at' => now(),
        ]);
    }
}
