<?php

namespace Tests\Feature\Tenancy;

use App\Models\Shop;
use App\Models\User;
use App\Tenancy\CurrentShop;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class CurrentShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_receives_their_active_shop_context(): void
    {
        $shop = Shop::factory()->create(['name' => 'My Farm Store']);
        $user = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertViewHas('shop', fn (Shop $resolvedShop): bool => $resolvedShop->is($shop));

        $this->assertSame($shop->id, app(CurrentShop::class)->id());
    }

    public function test_user_cannot_be_reassigned_to_another_shop(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $user = User::factory()->create([
            'shop_id' => $shopA->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $user->shop_id = $shopB->id;

        $this->expectException(AuthorizationException::class);

        $user->save();
    }

    public function test_bulk_user_update_cannot_change_shop_ownership(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $user = User::factory()->create([
            'shop_id' => $shopA->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $this->expectException(AuthorizationException::class);

        User::query()->whereKey($user->id)->update([
            'shop_id' => $shopB->id,
        ]);
    }

    public function test_direct_user_builder_insert_get_id_is_blocked(): void
    {
        $shop = Shop::factory()->create();

        $this->expectException(LogicException::class);

        User::query()->insertGetId([
            'shop_id' => $shop->id,
            'name' => 'Injected User',
            'mobile' => '9999999999',
            'password' => bcrypt('password'),
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);
    }

    public function test_dashboard_does_not_use_a_client_supplied_shop_id(): void
    {
        $userShop = Shop::factory()->create(['name' => 'User Shop']);
        $otherShop = Shop::factory()->create(['name' => 'Other Shop']);
        $user = User::factory()->create([
            'shop_id' => $userShop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['shop_id' => $otherShop->id]))
            ->assertOk()
            ->assertViewHas('shop', fn (Shop $resolvedShop): bool => $resolvedShop->is($userShop));
    }

    public function test_authenticated_user_without_a_shop_is_forbidden_from_tenant_routes(): void
    {
        $user = User::factory()->create([
            'shop_id' => null,
            'role' => User::ROLE_PLATFORM_OWNER,
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    public function test_user_of_a_suspended_shop_is_forbidden_from_tenant_routes(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_SUSPENDED]);
        $user = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    public function test_inactive_user_is_forbidden_from_tenant_routes(): void
    {
        $shop = Shop::factory()->create();
        $user = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => false,
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }
}
