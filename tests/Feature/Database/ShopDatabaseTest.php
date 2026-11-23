<?php

namespace Tests\Feature\Database;

use App\Models\Shop;
use App\Models\ShopSetting;
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

        ShopSetting::create([
            'shop_id' => $shop->id,
            'setting_key' => 'ledger.language',
            'setting_value' => 'hi',
        ]);

        $this->expectException(QueryException::class);

        ShopSetting::create([
            'shop_id' => $shop->id,
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
