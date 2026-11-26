<?php

namespace Tests\Feature\Financial;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\InterestCalculationRun;
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

        $firstPolicyVersion = DB::table('interest_policy_versions')->insertGetId([
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

        DB::table('interest_policy_versions')->insert([
            'shop_id' => $shop->getKey(),
            'interest_policy_id' => $policyId,
            'version_number' => 2,
            'effective_from' => '2026-10-15',
            'effective_to' => null,
            'monthly_rate_percent' => '6.00000000',
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

        $settlementKeyId = DB::table('idempotency_keys')->insertGetId([
            'shop_id' => $shop->getKey(),
            'operation_type' => 'ANNUAL_AUTO_SETTLEMENT',
            'key_hash' => hash('sha256', 'settlement-key-001'),
            'request_hash' => hash('sha256', 'settlement-payload-001'),
            'status' => 'COMPLETED',
            'resource_type' => 'interest_settlement_run',
            'resource_id' => 1,
            'response_code' => 201,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $settlement = InterestSettlementRun::query()->create([
            'customer_id' => $customer->getKey(),
            'settlement_date' => '2026-11-01',
            'cutoff_date_exclusive' => '2026-11-01',
            'shop_policy_version_id' => $firstPolicyVersion,
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

        $account = CustomerAccount::query()->where('customer_id', $customer->getKey())->firstOrFail();
        $this->assertSame('1000.00', $account->principal_balance);
        $this->assertSame('47.00', $account->interest_balance);
        $this->assertSame('1047.00', $account->total_payable);
        $this->assertDatabaseCount('ledger_entries', 3);

        $replayed = $service->calculateForSettlement($owner, $customer, $settlement->fresh());
        $this->assertSame($run->getKey(), $replayed->getKey());
        $this->assertDatabaseCount('interest_calculation_runs', 1);
        $this->assertDatabaseCount('interest_calculation_items', 2);
        $this->assertDatabaseCount('ledger_entries', 3);
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
