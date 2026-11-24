<?php

namespace Tests\Feature\UserManagement;

use App\Models\Shop;
use App\Models\User;
use App\Modules\Users\Application\ShopUserCapacity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShopUserCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_wide_active_user_limit_is_enforced_independently_of_role_limits(): void
    {
        $shop = Shop::factory()->create();
        User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);
        User::factory()->count(2)->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);
        User::factory()->count(4)->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);
        User::factory()->create([
            'shop_id' => $shop->id,
            'role' => 'legacy_role',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(ShopUserCapacity::class)->assertCanAdd($shop, User::ROLE_STAFF);
    }

    public function test_reactivation_is_rejected_when_role_capacity_is_full(): void
    {
        $shop = Shop::factory()->create();
        $target = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_MANAGER,
            'is_active' => false,
        ]);
        User::factory()->count(2)->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(ShopUserCapacity::class)->assertCanTransition(
            $shop,
            $target,
            true,
            User::ROLE_MANAGER,
        );
    }

    public function test_reactivation_is_rejected_when_total_capacity_is_full(): void
    {
        $shop = Shop::factory()->create();
        $target = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_STAFF,
            'is_active' => false,
        ]);
        User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);
        User::factory()->count(2)->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);
        User::factory()->count(4)->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);
        User::factory()->create([
            'shop_id' => $shop->id,
            'role' => 'legacy_role',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(ShopUserCapacity::class)->assertCanTransition(
            $shop,
            $target,
            true,
            User::ROLE_STAFF,
        );
    }

    public function test_active_role_change_releases_source_capacity_before_checking_destination(): void
    {
        $shop = Shop::factory()->create();
        $target = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);
        User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);
        User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);
        User::factory()->count(4)->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        app(ShopUserCapacity::class)->assertCanTransition(
            $shop,
            $target,
            true,
            User::ROLE_STAFF,
        );

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);
    }

    public function test_active_role_change_is_rejected_when_destination_role_is_full(): void
    {
        $shop = Shop::factory()->create();
        $target = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);
        User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);
        User::factory()->count(2)->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(ShopUserCapacity::class)->assertCanTransition(
            $shop,
            $target,
            true,
            User::ROLE_MANAGER,
        );
    }

    public function test_deactivation_releases_role_and_total_capacity_for_future_reuse(): void
    {
        $shop = Shop::factory()->create();
        $target = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);
        User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);
        User::factory()->count(2)->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        app(ShopUserCapacity::class)->assertCanTransition(
            $shop,
            $target,
            false,
            User::ROLE_MANAGER,
        );
    }

    public function test_capacity_transition_rejects_a_user_from_another_shop(): void
    {
        $shop = Shop::factory()->create();
        $otherShop = Shop::factory()->create();
        $target = User::factory()->create([
            'shop_id' => $otherShop->id,
            'role' => User::ROLE_STAFF,
            'is_active' => false,
        ]);

        $this->expectException(ValidationException::class);

        app(ShopUserCapacity::class)->assertCanTransition(
            $shop,
            $target,
            true,
            User::ROLE_STAFF,
        );
    }

    public function test_platform_role_cannot_consume_shop_user_capacity(): void
    {
        $shop = Shop::factory()->create();

        $this->expectException(ValidationException::class);

        app(ShopUserCapacity::class)->assertCanAdd($shop, User::ROLE_PLATFORM_OWNER);
    }
}
