<?php

namespace Tests\Feature\Financial;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\InterestSettlementRun;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Modules\Financial\Application\InterestCalculationService;
use App\Modules\Financial\Application\SalePostingService;
use App\Tenancy\CurrentShop;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InterestCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_annual_interest_calculation_segments_historical_policy_and_replays_idempotently(): void
    {
        [$shop, $owner, $customer] = $this->makeCustomerContext();
        $sale = app(SalePostingService::class)->post(
            $owner,
            $customer,
            Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-10-01'),
            '1000.00',
            'Interest calculation test',
            'interest-sale-001',
        );

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
            'effective_to' => '2026-10-15',
            'monthly_rate_percent' => '3.00000000',
            'grace_days' => 0,
            'capitalization_enabled' => false,
            'payment_waterfall' => 'PRINCIPAL_FIRST',
            'annual_settlement_month_1' => 3,
            'annual_settlement_day_1' => 30,
            'annual_settlement_month_2' => 10,
            'annual_settlement_day_2' => 30,
            'created_by' => $owner->getKey(),
            'created_at' => now(),
        ]);

        $secondPolicyVersion = DB::table('interest_policy_versions')->insertGetId([
            'shop_id' => $shop->getKey(),
            'interest_policy_id' => $policyId,
            'version_number' => 2,
            'effective_from' => '2026-10-15',
            'effective_to' => null,
            'monthly_rate_percent' => '6.00000000',
            'grace_days' => 0,
            'capitalization_enabled' => true,
            'payment_waterfall' => 'PRINCIPAL_FIRST',
            'annual_settlement_month_1' => 3,
            'annual_settlement_day_1' => 30,
            'annual_settlement_month_2' => 10,
            'annual_settlement_day_2' => 30,
            'created_by' => $owner->getKey(),
            'created_at' => now(),
        ]);

        $settlementKeyId = DB::table('idempotency_keys')->insertGetId([
            'shop_id' => $shop->getKey(),
            'operation_type' => 'ANNUAL_AUTO_SETTLEMENT',
            'key_hash' => hash('sha256', 'settlement-key-001'),
            'request_hash' => hash('sha256', 'settlement-payload-001'),
            'status' => 'IN_PROGRESS',
            'resource_type' => null,
            'resource_id' => null,
            'response_code' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $settlement = InterestSettlementRun::query()->create([
            'customer_id' => $customer->getKey(),
            'settlement_date' => '2026-11-01',
            'cutoff_date_exclusive' => '2026-11-01',
            'shop_policy_version_id' => $secondPolicyVersion,
            'status' => 'PENDING',
            'idempotency_key_id' => $settlementKeyId,
            'started_at' => now(),
        ]);

        $service = app(InterestCalculationService::class);
        $run = $service->calculateForSettlement($owner, $customer, $settlement);

        $this->assertSame('COMPLETED', $run->status);
        $this->assertSame('COMPLETED', $settlement->fresh()->status);
        $this->assertDatabaseCount('interest_calculation_items', 2);
        $this->assertDatabaseCount('interest_lots', 2);
        $this->assertDatabaseCount('interest_lot_events', 2);
        $this->assertDatabaseCount('interest_settlement_items', 2);

        $items = $run->items()->orderBy('interval_end_date_exclusive')->get();
        $this->assertSame(13, $items[0]->actual_days);
        $this->assertSame('3.00000000', $items[0]->monthly_rate_percent);
        $this->assertSame('13.00', $items[0]->posted_interest);
        $this->assertSame(17, $items[1]->actual_days);
        $this->assertSame('6.00000000', $items[1]->monthly_rate_percent);
        $this->assertSame('34.00', $items[1]->posted_interest);
        $this->assertTrue($items[1]->capitalization_enabled);

        $account = CustomerAccount::query()->where('customer_id', $customer->getKey())->firstOrFail();
        $this->assertSame('1034.00', $account->principal_balance);
        $this->assertSame('13.00', $account->interest_balance);
        $this->assertSame('1047.00', $account->total_payable);
        $this->assertDatabaseCount('ledger_entries', 5);

        $replayed = $service->calculateForSettlement($owner, $customer, $settlement->fresh());
        $this->assertSame($run->getKey(), $replayed->getKey());
        $this->assertDatabaseCount('interest_calculation_runs', 1);
        $this->assertDatabaseCount('interest_calculation_items', 2);
        $this->assertDatabaseCount('ledger_entries', 5);
    }

    public function test_customer_interest_history_api_is_read_only_scoped_and_explains_calculation_items(): void
    {
        [$shop, $owner, $customer] = $this->makeCustomerContext();
        $sale = app(SalePostingService::class)->post(
            $owner, $customer, Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-10-01'), '1000.00',
            'Interest history API test', 'interest-history-sale-001',
        );

        $policyId = DB::table('interest_policies')->insertGetId([
            'shop_id' => $shop->getKey(), 'policy_code' => 'DEFAULT',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $policyVersionId = DB::table('interest_policy_versions')->insertGetId([
            'shop_id' => $shop->getKey(), 'interest_policy_id' => $policyId,
            'version_number' => 1, 'effective_from' => '2026-01-01',
            'effective_to' => null, 'monthly_rate_percent' => '2.00000000',
            'grace_days' => 30, 'capitalization_enabled' => false,
            'payment_waterfall' => 'PRINCIPAL_FIRST',
            'annual_settlement_month_1' => 3, 'annual_settlement_day_1' => 30,
            'annual_settlement_month_2' => 10, 'annual_settlement_day_2' => 30,
            'created_by' => $owner->getKey(), 'created_at' => now(),
        ]);
        $settlementKeyId = DB::table('idempotency_keys')->insertGetId([
            'shop_id' => $shop->getKey(), 'operation_type' => 'ANNUAL_AUTO_SETTLEMENT',
            'key_hash' => hash('sha256', 'interest-history-settlement-key'),
            'request_hash' => hash('sha256', 'interest-history-settlement-payload'),
            'status' => 'IN_PROGRESS', 'resource_type' => null, 'resource_id' => null,
            'response_code' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $settlement = InterestSettlementRun::query()->create([
            'customer_id' => $customer->getKey(),
            'settlement_date' => '2026-11-02',
            'cutoff_date_exclusive' => '2026-11-02',
            'shop_policy_version_id' => $policyVersionId,
            'status' => 'PENDING',
            'idempotency_key_id' => $settlementKeyId,
            'started_at' => now(),
        ]);
        $run = app(InterestCalculationService::class)->calculateForSettlement($owner, $customer, $settlement);

        $staff = User::factory()->create([
            'shop_id' => $shop->getKey(), 'role' => User::ROLE_STAFF, 'is_active' => true,
        ]);
        Sanctum::actingAs($staff, ['app']);

        $runCount = DB::table('interest_calculation_runs')->where('shop_id', $shop->getKey())->count();
        $itemCount = DB::table('interest_calculation_items')->where('shop_id', $shop->getKey())->count();
        $this->getJson('/api/v1/customers/'.$customer->getKey().'/interest-history')
            ->assertOk()
            ->assertJsonPath('data.0.id', $run->getKey())
            ->assertJsonPath('data.0.items_count', 1)
            ->assertJsonPath('data.0.posted_interest_total', '0.67');
        $this->getJson('/api/v1/customers/'.$customer->getKey().'/interest-history/'.$run->getKey())
            ->assertOk()
            ->assertJsonPath('data.items.0.actual_days', 1)
            ->assertJsonPath('data.items.0.grace_days_applied', 30)
            ->assertJsonPath('data.items.0.monthly_rate_percent', '2.00000000')
            ->assertJsonPath('data.items.0.eligible_principal_basis', '1000.00000000')
            ->assertJsonPath('data.items.0.unrounded_interest', '0.66666667')
            ->assertJsonPath('data.items.0.posted_interest', '0.67')
            ->assertJsonPath('data.settlement.id', $settlement->getKey())
            ->assertJsonPath('data.items.0.shop_policy.version', 1)
            ->assertJsonPath('data.items.0.sale.id', $sale->getKey());

        $this->getJson('/api/v1/customers/'.$customer->getKey().'/interest-history?trigger_type=CUSTOMER_PAYMENT')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/customers/'.$customer->getKey().'/interest-history?from=2026-11-03&to=2026-11-04')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/customers/'.$customer->getKey().'/interest-history?per_page=5')->assertUnprocessable();
        $this->postJson('/api/v1/customers/'.$customer->getKey().'/interest-history')->assertStatus(405);

        $this->assertSame($runCount, DB::table('interest_calculation_runs')->where('shop_id', $shop->getKey())->count());
        $this->assertSame($itemCount, DB::table('interest_calculation_items')->where('shop_id', $shop->getKey())->count());

        [, , $foreignCustomer] = $this->makeCustomerContext();
        app(CurrentShop::class)->set($shop);
        $this->getJson('/api/v1/customers/'.$foreignCustomer->getKey().'/interest-history')->assertNotFound();
    }

    /**
     * @return array{Shop, User, Customer}
     */
    private function makeCustomerContext(): array
    {
        $shop = Shop::factory()->create();
        $owner = User::factory()->create([
            'shop_id' => $shop->getKey(),
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        app(CurrentShop::class)->set($shop);

        $customerId = DB::table('customers')->insertGetId([
            'shop_id' => $shop->getKey(),
            'name_original' => 'रामलाल',
            'name_original_language' => 'hi',
            'name_hi' => 'रामलाल',
            'name_en' => 'Ramlal',
            'mobile' => '9876543210',
            'status' => Customer::STATUS_ACTIVE,
            'created_by' => $owner->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$shop, $owner, Customer::query()->findOrFail($customerId)];
    }
}
