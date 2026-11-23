<?php

namespace Tests\Feature\Tenancy;

use App\Models\Shop;
use App\Models\ShopSetting;
use App\Tenancy\CurrentShop;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class TenantOwnedModelIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_settings_are_only_visible_to_the_current_shop(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        app(CurrentShop::class)->set($shopA);
        $settingA = $shopA->settings()->create([
            'setting_key' => 'language',
            'setting_value' => 'hi',
        ]);

        app(CurrentShop::class)->set($shopB);
        $settingB = $shopB->settings()->create([
            'setting_key' => 'language',
            'setting_value' => 'en',
        ]);

        app(CurrentShop::class)->set($shopA);

        $this->assertSame([$settingA->id], ShopSetting::query()->pluck('id')->all());
        $this->assertNull(ShopSetting::query()->find($settingB->id));
    }

    public function test_tenant_owned_queries_fail_closed_without_current_shop_context(): void
    {
        Shop::factory()->create();

        $this->assertSame(0, ShopSetting::query()->count());
    }

    public function test_tenant_owned_records_cannot_be_created_without_current_shop_context(): void
    {
        $this->expectException(LogicException::class);

        ShopSetting::create([
            'setting_key' => 'language',
            'setting_value' => 'hi',
        ]);
    }

    public function test_tenant_owned_record_cannot_be_created_for_another_shop(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        app(CurrentShop::class)->set($shopA);

        $setting = new ShopSetting([
            'setting_key' => 'language',
            'setting_value' => 'hi',
        ]);
        $setting->shop_id = $shopB->id;

        $this->expectException(AuthorizationException::class);

        $setting->save();
    }

    public function test_tenant_owned_record_cannot_be_moved_to_another_shop(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        app(CurrentShop::class)->set($shopA);

        $setting = $shopA->settings()->create([
            'setting_key' => 'language',
            'setting_value' => 'hi',
        ]);
        $setting->shop_id = $shopB->id;

        $this->expectException(AuthorizationException::class);

        $setting->save();
    }

    public function test_another_shops_record_cannot_be_updated_even_when_scope_is_explicitly_bypassed(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        app(CurrentShop::class)->set($shopB);
        $settingB = $shopB->settings()->create([
            'setting_key' => 'language',
            'setting_value' => 'en',
        ]);

        app(CurrentShop::class)->set($shopA);
        $unscopedRecord = ShopSetting::withoutGlobalScope('current_shop')->findOrFail($settingB->id);
        $unscopedRecord->setting_value = 'tampered';

        $this->expectException(AuthorizationException::class);

        $unscopedRecord->save();
    }

    public function test_another_shops_record_cannot_be_deleted_even_when_scope_is_explicitly_bypassed(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        app(CurrentShop::class)->set($shopB);
        $settingB = $shopB->settings()->create([
            'setting_key' => 'language',
            'setting_value' => 'en',
        ]);

        app(CurrentShop::class)->set($shopA);
        $unscopedRecord = ShopSetting::withoutGlobalScope('current_shop')->findOrFail($settingB->id);

        $this->expectException(AuthorizationException::class);

        $unscopedRecord->delete();
    }
}
