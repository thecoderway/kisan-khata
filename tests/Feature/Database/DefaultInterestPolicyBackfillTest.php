<?php

namespace Tests\Feature\Database;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DefaultInterestPolicyBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_backfills_missing_default_policy_using_approved_shop_defaults(): void
    {
        $shop = Shop::factory()->create(['timezone' => 'Asia/Kolkata']);
        $owner = User::factory()->create([
            'shop_id' => $shop->getKey(),
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $migration = require database_path(
            'migrations/2026_11_27_000001_backfill_default_interest_policy_for_existing_shops.php'
        );
        $migration->up();

        $policy = DB::table('interest_policies')
            ->where('shop_id', $shop->getKey())
            ->where('policy_code', 'DEFAULT')
            ->first();

        $this->assertNotNull($policy);
        $this->assertDatabaseHas('interest_policy_versions', [
            'shop_id' => $shop->getKey(),
            'interest_policy_id' => $policy->id,
            'version_number' => 1,
            'effective_from' => now('Asia/Kolkata')->toDateString(),
            'monthly_rate_percent' => '0.00000000',
            'grace_days' => 30,
            'capitalization_enabled' => false,
            'payment_waterfall' => 'PRINCIPAL_FIRST',
            'annual_settlement_month_1' => 3,
            'annual_settlement_day_1' => 30,
            'annual_settlement_month_2' => 10,
            'annual_settlement_day_2' => 30,
            'created_by' => $owner->getKey(),
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'shop_id' => $shop->getKey(),
            'user_id' => $owner->getKey(),
            'action' => 'financial.shop_interest_policy_initialized',
            'entity_type' => 'interest_policy_version',
        ]);

        $migration->up();

        $this->assertDatabaseCount('interest_policies', 1);
        $this->assertDatabaseCount('interest_policy_versions', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_migration_preserves_existing_shop_policy_versions(): void
    {
        $shop = Shop::factory()->create(['timezone' => 'Asia/Kolkata']);
        $owner = User::factory()->create([
            'shop_id' => $shop->getKey(),
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);
        $policyId = DB::table('interest_policies')->insertGetId([
            'shop_id' => $shop->getKey(),
            'policy_code' => 'DEFAULT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('interest_policy_versions')->insert([
            'shop_id' => $shop->getKey(),
            'interest_policy_id' => $policyId,
            'version_number' => 1,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'monthly_rate_percent' => '2.50000000',
            'grace_days' => 15,
            'capitalization_enabled' => true,
            'payment_waterfall' => 'INTEREST_FIRST',
            'annual_settlement_month_1' => 3,
            'annual_settlement_day_1' => 30,
            'annual_settlement_month_2' => 10,
            'annual_settlement_day_2' => 30,
            'created_by' => $owner->getKey(),
            'created_at' => now(),
        ]);

        $migration = require database_path(
            'migrations/2026_11_27_000001_backfill_default_interest_policy_for_existing_shops.php'
        );
        $migration->up();

        $this->assertDatabaseCount('interest_policy_versions', 1);
        $this->assertDatabaseHas('interest_policy_versions', [
            'shop_id' => $shop->getKey(),
            'interest_policy_id' => $policyId,
            'version_number' => 1,
            'monthly_rate_percent' => '2.50000000',
            'grace_days' => 15,
            'capitalization_enabled' => true,
            'payment_waterfall' => 'INTEREST_FIRST',
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'shop_id' => $shop->getKey(),
            'action' => 'financial.shop_interest_policy_initialized',
        ]);
    }
}
