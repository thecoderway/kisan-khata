<?php

namespace Tests\Feature\Database;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_and_shop_relationships_work(): void
    {
        $shop = Shop::factory()->create();
        $user = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
        ]);

        $this->assertTrue($user->shop->is($shop));
        $this->assertTrue($shop->users->contains($user));
    }

    public function test_shop_settings_are_unique_per_shop_and_key(): void
    {
        $shop = Shop::factory()->create();

        $shop->settings()->create([
            'setting_key' => 'ledger.language',
            'setting_value' => 'hi',
        ]);

        $this->expectException(QueryException::class);

        $shop->settings()->create([
            'setting_key' => 'ledger.language',
            'setting_value' => 'en',
        ]);
    }

    public function test_user_email_and_mobile_can_be_null(): void
    {
        $shop = Shop::factory()->create();

        $user = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_STAFF,
            'email' => null,
            'mobile' => null,
        ]);

        $this->assertNull($user->email);
        $this->assertNull($user->mobile);
    }

    public function test_role_and_active_status_are_not_mass_assignable(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        $user->fill([
            'role' => User::ROLE_PLATFORM_OWNER,
            'is_active' => false,
        ]);

        $this->assertSame(User::ROLE_STAFF, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_shop_status_is_not_mass_assignable(): void
    {
        $shop = Shop::factory()->create([
            'status' => Shop::STATUS_ACTIVE,
        ]);

        $shop->fill([
            'status' => Shop::STATUS_SUSPENDED,
        ]);

        $this->assertSame(Shop::STATUS_ACTIVE, $shop->status);
    }

    public function test_a_shop_can_have_multiple_settings_with_distinct_keys(): void
    {
        $shop = Shop::factory()->create();

        $shop->settings()->createMany([
            ['setting_key' => 'language', 'setting_value' => 'hi'],
            ['setting_key' => 'timezone', 'setting_value' => 'Asia/Kolkata'],
        ]);

        $this->assertCount(2, $shop->settings);
    }
}
