<?php

namespace Tests\Feature\Database;

use App\Models\Customer;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GroupsFiveToSevenInterestSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_calculation_lot_and_settlement_tables_use_innodb(): void
    {
        $tables = [
            'interest_policies',
            'interest_policy_versions',
            'customer_interest_policy_versions',
            'interest_calculation_runs',
            'interest_calculation_items',
            'interest_lots',
            'interest_lot_events',
            'interest_settlement_runs',
            'interest_settlement_items',
        ];

        foreach ($tables as $table) {
            $engine = DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->value('ENGINE');

            $this->assertSame('InnoDB', $engine);
        }

        $this->assertContains(
            'ipv_shop_policy_version_unique',
            collect(Schema::getIndexes('interest_policy_versions'))->pluck('name')
        );
        $this->assertContains(
            'icr_shop_event_key_unique',
            collect(Schema::getIndexes('interest_calculation_runs'))->pluck('name')
        );
        $this->assertContains(
            'il_shop_customer_sale_lot_date_idx',
            collect(Schema::getIndexes('interest_lots'))->pluck('name')
        );
        $this->assertContains(
            'il_shop_customer_calculation_item_unique',
            collect(Schema::getIndexes('interest_lots'))->pluck('name')
        );
        $this->assertContains(
            'isr_shop_customer_date_unique',
            collect(Schema::getIndexes('interest_settlement_runs'))->pluck('name')
        );
    }

    public function test_customer_policy_version_rejects_empty_override_and_invalid_effective_range(): void
    {
        [$shop, $customer, $actor] = $this->fixture();

        $this->expectException(QueryException::class);
        DB::table('customer_interest_policy_versions')->insert([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'version_number' => 1,
            'effective_from' => '2026-11-01',
            'effective_to' => null,
            'monthly_rate_percent' => null,
            'capitalization_enabled' => null,
            'created_by' => $actor->id,
            'created_at' => now(),
        ]);
    }

    public function test_shop_policy_versions_store_approved_initial_defaults(): void
    {
        [$shop, $customer, $actor] = $this->fixture();
        $policyId = (int) DB::table('interest_policies')->insertGetId([
            'shop_id' => $shop->id,
            'policy_code' => 'DEFAULT_INTEREST',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('interest_policy_versions')->insert([
            'shop_id' => $shop->id,
            'interest_policy_id' => $policyId,
            'version_number' => 1,
            'effective_from' => '2026-11-26',
            'effective_to' => null,
            'monthly_rate_percent' => '0.00000000',
            'grace_days' => 30,
            'capitalization_enabled' => false,
            'payment_waterfall' => 'PRINCIPAL_FIRST',
            'annual_settlement_month_1' => 3,
            'annual_settlement_day_1' => 30,
            'annual_settlement_month_2' => 10,
            'annual_settlement_day_2' => 30,
            'created_by' => $actor->id,
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('interest_policy_versions', [
            'shop_id' => $shop->id,
            'interest_policy_id' => $policyId,
            'monthly_rate_percent' => '0.00000000',
            'grace_days' => 30,
            'capitalization_enabled' => 0,
            'payment_waterfall' => 'PRINCIPAL_FIRST',
            'annual_settlement_month_1' => 3,
            'annual_settlement_day_1' => 30,
            'annual_settlement_month_2' => 10,
            'annual_settlement_day_2' => 30,
        ]);
    }

    private function fixture(): array
    {
        $shop = Shop::factory()->create();
        $actor = User::factory()->create(['shop_id' => $shop->id, 'role' => User::ROLE_OWNER]);
        $id = (int) DB::table('customers')->insertGetId([
            'shop_id' => $shop->id,
            'created_by' => $actor->id,
            'name_original' => 'Interest Policy Customer',
            'name_original_language' => 'en',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$shop, Customer::withoutGlobalScopes()->findOrFail($id), $actor];
    }
}
