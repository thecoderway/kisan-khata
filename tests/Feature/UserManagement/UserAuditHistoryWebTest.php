<?php

namespace Tests\Feature\UserManagement;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserAuditHistoryWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_search_audit_history_by_actor_target_and_reason(): void
    {
        $shop = Shop::factory()->create();
        $owner = $this->userFor($shop, User::ROLE_OWNER, 'Audit Owner');
        $actor = $this->userFor($shop, User::ROLE_MANAGER, 'Audit Actor');
        $target = $this->userFor($shop, User::ROLE_STAFF, 'Audit Target');

        $this->createAudit($shop, $actor, $target, 'user.deactivated', 'Duplicate account');

        $this->actingAs($owner)
            ->get(route('users.audit', ['q' => 'Audit Actor']))
            ->assertOk()
            ->assertSee('Audit Actor')
            ->assertSee('Audit Target');

        $this->actingAs($owner)
            ->get(route('users.audit', ['q' => 'Duplicate account']))
            ->assertOk()
            ->assertSee('Duplicate account');
    }

    public function test_owner_can_filter_audit_history_by_action(): void
    {
        $shop = Shop::factory()->create();
        $owner = $this->userFor($shop, User::ROLE_OWNER, 'Audit Owner');
        $actor = $this->userFor($shop, User::ROLE_MANAGER, 'Audit Actor');
        $target = $this->userFor($shop, User::ROLE_STAFF, 'Audit Target');

        $this->createAudit($shop, $actor, $target, 'user.deactivated', 'Deactivation reason');
        $this->createAudit($shop, $actor, $target, 'user.reactivated', 'Reactivation reason');

        $this->actingAs($owner)
            ->get(route('users.audit', ['action' => 'user.deactivated']))
            ->assertOk()
            ->assertSee('Deactivation reason')
            ->assertDontSee('Reactivation reason');
    }

    public function test_audit_history_pagination_preserves_search_and_action_filters(): void
    {
        $shop = Shop::factory()->create();
        $owner = $this->userFor($shop, User::ROLE_OWNER, 'Audit Owner');
        $actor = $this->userFor($shop, User::ROLE_MANAGER, 'Audit Actor');
        $target = $this->userFor($shop, User::ROLE_STAFF, 'Audit Target');

        for ($index = 1; $index <= 21; $index++) {
            $this->createAudit($shop, $actor, $target, 'user.deactivated', 'Shared audit reason '.$index);
        }
        $this->createAudit($shop, $actor, $target, 'user.reactivated', 'Different action');

        $this->actingAs($owner)
            ->get(route('users.audit', [
                'q' => 'Shared audit reason',
                'action' => 'user.deactivated',
                'pageSize' => 10,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertSee('Shared audit reason')
            ->assertDontSee('Different action')
            ->assertSee('page=1')
            ->assertViewHas('logs', fn ($logs): bool => $logs->currentPage() === 2 && $logs->total() === 21);
    }

    public function test_audit_history_fragment_returns_filtered_paginated_results(): void
    {
        $shop = Shop::factory()->create();
        $owner = $this->userFor($shop, User::ROLE_OWNER, 'Audit Owner');
        $actor = $this->userFor($shop, User::ROLE_MANAGER, 'Audit Actor');
        $target = $this->userFor($shop, User::ROLE_STAFF, 'Audit Target');

        for ($index = 1; $index <= 12; $index++) {
            $this->createAudit($shop, $actor, $target, 'user.deactivated', 'Fragment reason '.$index);
        }

        $this->actingAs($owner)
            ->getJson(route('users.audit', [
                'fragment' => 1,
                'q' => 'Fragment reason',
                'action' => 'user.deactivated',
                'pageSize' => 10,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('html', fn (string $html): bool => str_contains($html, 'Showing <strong>11–12</strong>'));
    }

    private function userFor(Shop $shop, string $role, string $name): User
    {
        return User::factory()->create([
            'shop_id' => $shop->id,
            'role' => $role,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function createAudit(Shop $shop, User $actor, User $target, string $action, string $reason): void
    {
        DB::table('user_audit_logs')->insert([
            'shop_id' => $shop->id,
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'action' => $action,
            'old_values' => json_encode(['is_active' => true], JSON_THROW_ON_ERROR),
            'new_values' => json_encode(['is_active' => false], JSON_THROW_ON_ERROR),
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
