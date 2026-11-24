<?php

namespace Tests\Feature\Tenancy;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantMiddlewareIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_business_routes_require_tenant_middleware_but_logout_does_not(): void
    {
        $dashboard = Route::getRoutes()->match(Request::create('/dashboard', 'GET'));
        $apiUser = Route::getRoutes()->match(Request::create('/api/user', 'GET'));
        $webLogout = Route::getRoutes()->match(Request::create('/logout', 'POST'));
        $apiLogout = Route::getRoutes()->match(Request::create('/api/logout', 'POST'));

        $this->assertContains('auth', $dashboard->gatherMiddleware());
        $this->assertContains('tenant', $dashboard->gatherMiddleware());

        $this->assertContains('auth:sanctum', $apiUser->gatherMiddleware());
        $this->assertContains('abilities:app', $apiUser->gatherMiddleware());
        $this->assertContains('tenant', $apiUser->gatherMiddleware());

        $this->assertContains('auth', $webLogout->gatherMiddleware());
        $this->assertNotContains('tenant', $webLogout->gatherMiddleware());

        $this->assertContains('auth:sanctum', $apiLogout->gatherMiddleware());
        $this->assertNotContains('tenant', $apiLogout->gatherMiddleware());
    }

    public function test_dashboard_resolves_shop_from_authenticated_user_not_request_input(): void
    {
        $shopA = Shop::factory()->create(['name' => 'Shop A']);
        $shopB = Shop::factory()->create(['name' => 'Shop B']);
        $userA = $this->createOwnerFor($shopA);

        $this->actingAs($userA)
            ->get(route('dashboard', ['shop_id' => $shopB->id]))
            ->assertOk()
            ->assertViewHas('shop', fn (Shop $shop): bool => $shop->is($shopA));
    }

    public function test_dashboard_rejects_inactive_users(): void
    {
        $shop = Shop::factory()->create();
        $user = $this->createOwnerFor($shop, ['is_active' => false]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_dashboard_rejects_suspended_shops(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_SUSPENDED]);
        $user = $this->createOwnerFor($shop);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_api_user_endpoint_resolves_shop_from_authenticated_user_not_request_input(): void
    {
        $shopA = Shop::factory()->create(['name' => 'Shop A']);
        $shopB = Shop::factory()->create(['name' => 'Shop B']);
        $userA = $this->createOwnerFor($shopA);

        Sanctum::actingAs($userA, ['app']);

        $this->getJson('/api/user?shop_id='.$shopB->id)
            ->assertOk()
            ->assertJsonPath('shop.id', $shopA->id)
            ->assertJsonPath('shop.name', 'Shop A');
    }

    public function test_api_user_endpoint_rejects_inactive_users(): void
    {
        $shop = Shop::factory()->create();
        $user = $this->createOwnerFor($shop, ['is_active' => false]);

        Sanctum::actingAs($user, ['app']);

        $this->getJson('/api/user')->assertForbidden();
    }

    public function test_api_user_endpoint_rejects_suspended_shops(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_SUSPENDED]);
        $user = $this->createOwnerFor($shop);

        Sanctum::actingAs($user, ['app']);

        $this->getJson('/api/user')->assertForbidden();
    }

    public function test_tenant_context_is_resolved_again_for_each_authenticated_api_request(): void
    {
        $shopA = Shop::factory()->create(['name' => 'Shop A']);
        $shopB = Shop::factory()->create(['name' => 'Shop B']);
        $userA = $this->createOwnerFor($shopA);
        $userB = $this->createOwnerFor($shopB);

        Sanctum::actingAs($userA, ['app']);
        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('shop.id', $shopA->id);

        Sanctum::actingAs($userB, ['app']);
        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('shop.id', $shopB->id);
    }

    public function test_web_logout_remains_available_when_the_shop_is_suspended(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_SUSPENDED]);
        $user = $this->createOwnerFor($shop);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));
    }

    public function test_api_logout_remains_available_when_the_shop_is_suspended(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_SUSPENDED]);
        $user = $this->createOwnerFor($shop);

        Sanctum::actingAs($user);

        $this->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'लॉगआउट सफल हुआ।');
    }

    public function test_api_tenant_routes_reject_platform_roles_even_when_shop_is_assigned(): void
    {
        $shop = Shop::factory()->create();
        $platformUser = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_PLATFORM_OWNER,
            'is_active' => true,
        ]);

        Sanctum::actingAs($platformUser, ['app']);

        $this->getJson('/api/user')->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createOwnerFor(Shop $shop, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ], $attributes));
    }
}
