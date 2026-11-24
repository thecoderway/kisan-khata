<?php

namespace Tests\Feature\UserManagement;

use App\Models\Shop;
use App\Models\User;
use App\Modules\Users\Application\ManageShopUserLifecycle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ManageShopUserLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_deactivate_manager_and_revoke_all_tokens_and_database_sessions(): void
    {
        [$shop, $owner, $target] = $this->shopWithOwnerAndTarget(User::ROLE_MANAGER);

        $target->createToken('phone-one', ['app']);
        $target->createToken('phone-two', ['app']);
        DB::table(config('session.table', 'sessions'))->insert([
            'id' => 'target-session',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $result = app(ManageShopUserLifecycle::class)->deactivate($owner, $target->id, 'Left employment');

        $this->assertFalse($result->is_active);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseMissing(config('session.table', 'sessions'), ['id' => 'target-session']);
        $this->assertDatabaseHas('user_audit_logs', [
            'shop_id' => $shop->id,
            'actor_user_id' => $owner->id,
            'target_user_id' => $target->id,
            'action' => 'user.deactivated',
            'reason' => 'Left employment',
        ]);
    }

    public function test_owner_can_reactivate_user_and_account_history_is_retained(): void
    {
        [, $owner, $target] = $this->shopWithOwnerAndTarget(User::ROLE_STAFF, false);

        $result = app(ManageShopUserLifecycle::class)->reactivate($owner, $target->id, 'Returned');

        $this->assertTrue($result->is_active);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => true]);
        $this->assertDatabaseHas('user_audit_logs', [
            'target_user_id' => $target->id,
            'action' => 'user.reactivated',
            'reason' => 'Returned',
        ]);
    }

    public function test_reactivation_is_rejected_when_staff_capacity_is_full(): void
    {
        [$shop, $owner, $target] = $this->shopWithOwnerAndTarget(User::ROLE_STAFF, false);
        User::factory()->count(5)->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        try {
            app(ManageShopUserLifecycle::class)->reactivate($owner, $target->id);
            $this->fail('Expected capacity validation to reject reactivation.');
        } catch (ValidationException) {
            $this->assertFalse($target->fresh()->is_active);
            $this->assertDatabaseMissing('user_audit_logs', [
                'target_user_id' => $target->id,
                'action' => 'user.reactivated',
            ]);
        }
    }

    public function test_owner_can_change_manager_to_staff_and_audit_old_and_new_roles(): void
    {
        [, $owner, $target] = $this->shopWithOwnerAndTarget(User::ROLE_MANAGER);

        $result = app(ManageShopUserLifecycle::class)->changeRole($owner, $target->id, User::ROLE_STAFF, 'Role adjustment');

        $this->assertSame(User::ROLE_STAFF, $result->role);
        $audit = DB::table('user_audit_logs')->where('target_user_id', $target->id)->first();
        $this->assertSame('user.role_changed', $audit->action);
        $this->assertSame(['role' => User::ROLE_MANAGER], json_decode($audit->old_values, true));
        $this->assertSame(['role' => User::ROLE_STAFF], json_decode($audit->new_values, true));
    }

    public function test_owner_cannot_deactivate_themselves_and_cross_shop_targets_are_not_found(): void
    {
        [$shop, $owner] = $this->shopWithOwnerAndTarget(User::ROLE_STAFF);

        try {
            app(ManageShopUserLifecycle::class)->deactivate($owner, $owner->id);
            $this->fail('Expected self-deactivation to be denied.');
        } catch (AuthorizationException) {
            $this->assertTrue($owner->fresh()->is_active);
        }

        $otherShop = Shop::factory()->create();
        $otherUser = User::factory()->create([
            'shop_id' => $otherShop->id,
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(ManageShopUserLifecycle::class)->deactivate($owner, $otherUser->id);
    }

    private function shopWithOwnerAndTarget(string $role, bool $active = true): array
    {
        $shop = Shop::factory()->create();
        $owner = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);
        $target = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => $role,
            'is_active' => $active,
        ]);

        return [$shop, $owner, $target];
    }
}
