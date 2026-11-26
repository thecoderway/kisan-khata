<?php

namespace Tests\Feature\Financial;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\InterestSettlementRun;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Modules\Financial\Application\AnnualInterestSettlementService;
use App\Modules\Financial\Application\PaymentPostingService;
use App\Modules\Financial\Application\SalePostingService;
use App\Tenancy\CurrentShop;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnnualInterestSettlementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_settlement_posts_interest_once_and_is_safe_to_replay(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner, '3.00000000');

        app(SalePostingService::class)->post(
            $owner,
            $customer,
            Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-10-01'),
            '1000.00',
            'Annual settlement sale',
            'annual-settlement-sale-001',
        );

        $service = app(AnnualInterestSettlementService::class);
        $first = $service->settleShopDate($shop, '2026-10-30');
        $second = $service->settleShopDate($shop, '2026-10-30');

        $this->assertSame(1, $first['due']);
        $this->assertSame(1, $first['completed']);
        $this->assertSame(1, $second['due']);
        $this->assertSame(1, $second['completed']);
        $this->assertDatabaseCount('interest_settlement_runs', 1);
        $this->assertDatabaseCount('interest_calculation_runs', 1);
        $this->assertDatabaseCount('interest_settlement_items', 1);
        $this->assertDatabaseCount('ledger_entries', 2);
        $this->assertSame('COMPLETED', InterestSettlementRun::query()->firstOrFail()->status);
        $this->assertSame('29.00', CustomerAccount::query()->where('customer_id', $customer->getKey())->firstOrFail()->interest_balance);
    }

    public function test_normal_payment_on_settlement_date_is_processed_before_the_annual_checkpoint(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner, '3.00000000');

        app(SalePostingService::class)->post(
            $owner,
            $customer,
            Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-10-01'),
            '1000.00',
            'Same-day payment sale',
            'annual-settlement-payment-sale',
        );

        app(PaymentPostingService::class)->post(
            $owner,
            $customer,
            '100.00',
            CarbonImmutable::parse('2026-10-30'),
            'CASH',
            null,
            'Payment on settlement date',
            'annual-settlement-same-day-payment',
        );

        $result = app(AnnualInterestSettlementService::class)->settleShopDate($shop, '2026-10-30');

        $this->assertSame(1, $result['completed']);
        $this->assertDatabaseCount('interest_calculation_runs', 2);
        $this->assertDatabaseCount('interest_settlement_runs', 1);
        $account = CustomerAccount::query()->where('customer_id', $customer->getKey())->firstOrFail();
        $this->assertSame('29.00', $account->interest_balance);
        $this->assertSame('900.00', $account->principal_balance);
    }

    public function test_non_settlement_date_is_skipped_without_financial_writes(): void
    {
        [$shop, $owner] = $this->context();
        $this->createPolicy($shop, $owner, '3.00000000');

        $result = app(AnnualInterestSettlementService::class)->settleShopDate($shop, '2026-10-29');

        $this->assertSame(1, $result['skipped']);
        $this->assertDatabaseCount('interest_settlement_runs', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    private function context(): array
    {
        $shop = Shop::factory()->create(['timezone' => 'Asia/Kolkata']);
        $owner = User::factory()->create([
            'shop_id' => $shop->getKey(),
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        app(CurrentShop::class)->set($shop);

        $customerId = DB::table('customers')->insertGetId([
            'shop_id' => $shop->getKey(),
            'name_original' => 'Settlement Customer',
            'name_original_language' => 'en',
            'name_en' => 'Settlement Customer',
            'status' => Customer::STATUS_ACTIVE,
            'created_by' => $owner->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$shop, $owner, Customer::query()->findOrFail($customerId)];
    }

    private function createPolicy(Shop $shop, User $owner, string $rate): void
    {
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
            'monthly_rate_percent' => $rate,
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
    }
}
