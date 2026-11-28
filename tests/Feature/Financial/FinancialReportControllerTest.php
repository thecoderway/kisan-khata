<?php

namespace Tests\Feature\Financial;

use App\Models\Customer;
use App\Models\InterestSettlementRun;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Modules\Financial\Application\InterestCalculationService;
use App\Modules\Financial\Application\PaymentPostingService;
use App\Modules\Financial\Application\PaymentReversalService;
use App\Modules\Financial\Application\SalePostingService;
use App\Tenancy\CurrentShop;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_report_uses_inclusive_dates_and_summary_across_filtered_rows(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);

        $first = app(SalePostingService::class)->post(
            $owner, $customer, Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-11-01'), '100.00', 'First report sale', 'report-sales-first',
        );
        app(SalePostingService::class)->post(
            $owner, $customer, Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-11-02'), '200.00', 'Second report sale', 'report-sales-second',
        );

        Sanctum::actingAs($owner, ['app']);

        $this->getJson('/api/v1/reports/sales?from=2026-11-01&to=2026-11-02&per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('summary.sale_count', 2)
            ->assertJsonPath('summary.gross_sales', '300.00')
            ->assertJsonFragment(['sale_number' => $first->sale_number]);

        $this->getJson('/api/v1/reports/sales?from=2026-11-02&to=2026-11-02')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('summary.gross_sales', '200.00');
    }

    public function test_outstanding_report_uses_ledger_as_of_and_includes_zero_balance_customer(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);

        app(SalePostingService::class)->post(
            $owner, $customer, Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-11-01'), '100.00', 'Outstanding report sale', 'report-outstanding-sale',
        );
        app(PaymentPostingService::class)->post(
            $owner, $customer, '100.00', CarbonImmutable::parse('2026-11-02'),
            'CASH', null, 'Outstanding report payment', 'report-outstanding-payment',
        );

        Sanctum::actingAs($owner, ['app']);

        $this->getJson('/api/v1/reports/outstanding?as_of=2026-11-02')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.principal', '0.00')
            ->assertJsonPath('data.0.interest', '0.00')
            ->assertJsonPath('data.0.total', '0.00')
            ->assertJsonPath('summary.grand_total', '0.00');

        $this->getJson('/api/v1/reports/outstanding?as_of=2026-11-01')
            ->assertOk()
            ->assertJsonPath('data.0.principal', '100.00');
    }

    public function test_customer_id_filter_distinguishes_customers_with_identical_names(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);

        $duplicateId = DB::table('customers')->insertGetId([
            'shop_id' => $shop->getKey(),
            'name_original' => $customer->name_original,
            'name_original_language' => $customer->name_original_language,
            'name_hi' => $customer->name_hi,
            'name_en' => $customer->name_en,
            'status' => Customer::STATUS_ACTIVE,
            'created_by' => $owner->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $duplicate = Customer::query()->findOrFail($duplicateId);

        app(SalePostingService::class)->post(
            $owner, $customer, Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-11-01'), '100.00', 'First same-name customer sale', 'report-same-name-first',
        );
        app(SalePostingService::class)->post(
            $owner, $duplicate, Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-11-01'), '250.00', 'Second same-name customer sale', 'report-same-name-second',
        );
        app(PaymentPostingService::class)->post(
            $owner, $customer, '30.00', CarbonImmutable::parse('2026-11-02'),
            'CASH', null, 'First customer payment', 'report-same-name-payment',
        );

        Sanctum::actingAs($owner, ['app']);

        $this->getJson('/api/v1/reports/outstanding?as_of=2026-11-02&customer_id='.$customer->getKey())
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.customer_id', $customer->getKey())
            ->assertJsonPath('data.0.principal', '70.00');

        $this->getJson('/api/v1/reports/sales?customer_id='.$customer->getKey())
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.customer_id', $customer->getKey())
            ->assertJsonPath('summary.gross_sales', '100.00');

        $this->getJson('/api/v1/reports/payments?customer_id='.$customer->getKey())
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.customer_id', $customer->getKey());

        $this->getJson('/api/v1/reports/sales?customer_id='.$duplicate->getKey())
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.customer_id', $duplicate->getKey())
            ->assertJsonPath('summary.gross_sales', '250.00');
    }

    public function test_payment_report_separates_replacement_and_sale_day_auto_from_cash_collection(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);

        app(SalePostingService::class)->post(
            $owner, $customer, Sale::PAYMENT_CHOICE_PAID,
            CarbonImmutable::parse('2026-11-01'), '50.00', 'Auto settlement report sale', 'report-auto-sale',
        );
        app(SalePostingService::class)->post(
            $owner, $customer, Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-11-01'), '100.00', 'Normal receipt report sale', 'report-normal-sale',
        );
        $original = app(PaymentPostingService::class)->post(
            $owner, $customer, '20.00', CarbonImmutable::parse('2026-11-02'),
            'CASH', 'REPORT-ORIGINAL', 'Original receipt', 'report-original-payment',
        );
        app(PaymentReversalService::class)->correct(
            $owner, $customer, $original, 'Report replacement test', 'report-reversal',
            [
                'amount' => '20.00',
                'payment_method' => 'UPI',
                'reference_number' => 'REPORT-REPLACEMENT',
                'description' => 'Replacement receipt',
            ],
        );

        Sanctum::actingAs($owner, ['app']);

        $this->getJson('/api/v1/reports/payments?from=2026-11-01&to=2026-11-02')
            ->assertOk()
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('summary.normal_receipts', '20.00')
            ->assertJsonPath('summary.reversals', '20.00')
            ->assertJsonPath('summary.net_collection', '0.00')
            ->assertJsonPath('summary.unverified_replacements', '20.00')
            ->assertJsonPath('summary.auto_settlements', '50.00');
    }

    public function test_interest_report_counts_only_a_matching_interest_ledger_debit(): void
    {
        [$shop, $owner, $customer] = $this->context();
        DB::table('villages')->insert([
            'villageCode' => '1001', 'villageNameEnglish' => 'Lotiya', 'villageNameLocal' => 'लोटिया',
            'subdistrictCode' => '201', 'subdistrictNameEnglish' => 'Sarangpur', 'subdistrictNameLocal' => 'सारंगपुर',
            'districtCode' => '301', 'districtNameEnglish' => 'Rajgarh', 'districtNameLocal' => 'राजगढ़',
            'stateCode' => '23', 'stateNameEnglish' => 'Madhya Pradesh', 'stateNameLocal' => 'मध्य प्रदेश',
            'data_gov_update_date' => '21-04-2026',
        ]);
        DB::table('customers')->where('id', $customer->getKey())->update(['village_code' => '1001']);
        $sale = app(SalePostingService::class)->post(
            $owner, $customer, Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-10-01'), '1000.00', 'Interest report sale', 'report-interest-sale',
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
            'key_hash' => hash('sha256', 'report-interest-settlement-key'),
            'request_hash' => hash('sha256', 'report-interest-settlement-payload'),
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
        $item = $run->items()->firstOrFail();

        Sanctum::actingAs($owner, ['app']);

        $this->getJson('/api/v1/reports/interest?customer_id='.$customer->getKey().'&village_code=1001')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('summary.item_count', 1)
            ->assertJsonPath('summary.posted_interest_total', $item->posted_interest)
            ->assertJsonPath('data.0.customer_id', $customer->getKey())
            ->assertJsonPath('data.0.ledger_entry_id', $item->ledger_entry_id);

        DB::table('interest_calculation_items')->where('id', $item->getKey())
            ->update(['ledger_entry_id' => DB::table('ledger_entries')->where('sale_id', $sale->getKey())->value('id')]);

        $this->getJson('/api/v1/reports/interest')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('summary.item_count', 1)
            ->assertJsonPath('summary.posted_interest_total', '0.00')
            ->assertJsonPath('data.0.posted_date', null);
    }

    public function test_reports_are_shop_scoped_and_interest_report_is_owner_only(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);
        app(SalePostingService::class)->post(
            $owner, $customer, Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-11-01'), '100.00', 'Tenant report sale', 'report-tenant-sale',
        );

        [$foreignShop, $foreignOwner, $foreignCustomer] = $this->context();
        $this->createPolicy($foreignShop, $foreignOwner);
        app(SalePostingService::class)->post(
            $foreignOwner, $foreignCustomer, Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-11-01'), '999.00', 'Foreign report sale', 'report-foreign-sale',
        );

        app(CurrentShop::class)->set($shop);
        Sanctum::actingAs($owner, ['app']);
        $this->getJson('/api/v1/reports/sales')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('summary.gross_sales', '100.00');

        $staff = User::factory()->create([
            'shop_id' => $shop->getKey(), 'role' => User::ROLE_STAFF, 'is_active' => true,
        ]);
        Sanctum::actingAs($staff, ['app']);
        $this->getJson('/api/v1/reports/interest')->assertForbidden();
    }

    /**
     * @return array{Shop, User, Customer}
     */
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
            'name_original' => 'Financial Report Customer',
            'name_original_language' => 'en',
            'name_en' => 'Financial Report Customer',
            'status' => Customer::STATUS_ACTIVE,
            'created_by' => $owner->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$shop, $owner, Customer::query()->findOrFail($customerId)];
    }

    private function createPolicy(Shop $shop, User $owner): void
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
            'monthly_rate_percent' => '0.00000000',
            'grace_days' => 30,
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

    public function test_village_filter_scopes_sales_payments_and_financial_reports(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);
        DB::table('villages')->insert([
            ['villageCode' => '1001', 'villageNameEnglish' => 'Lotiya', 'villageNameLocal' => 'लोटिया',
                'subdistrictCode' => '201', 'subdistrictNameEnglish' => 'Sarangpur', 'subdistrictNameLocal' => 'सारंगपुर',
                'districtCode' => '301', 'districtNameEnglish' => 'Rajgarh', 'districtNameLocal' => 'राजगढ़',
                'stateCode' => '23', 'stateNameEnglish' => 'Madhya Pradesh', 'stateNameLocal' => 'मध्य प्रदेश',
                'data_gov_update_date' => '21-04-2026'],
            ['villageCode' => '1002', 'villageNameEnglish' => 'Biaora', 'villageNameLocal' => 'ब्यावरा',
                'subdistrictCode' => '202', 'subdistrictNameEnglish' => 'Biaora', 'subdistrictNameLocal' => 'ब्यावरा',
                'districtCode' => '301', 'districtNameEnglish' => 'Rajgarh', 'districtNameLocal' => 'राजगढ़',
                'stateCode' => '23', 'stateNameEnglish' => 'Madhya Pradesh', 'stateNameLocal' => 'मध्य प्रदेश',
                'data_gov_update_date' => '21-04-2026'],
        ]);
        DB::table('customers')->where('id', $customer->getKey())->update(['village_code' => '1001']);
        $otherId = DB::table('customers')->insertGetId([
            'shop_id' => $shop->getKey(), 'name_original' => 'Biaora Customer',
            'name_original_language' => 'en', 'name_en' => 'Biaora Customer',
            'village_code' => '1002', 'status' => Customer::STATUS_ACTIVE,
            'created_by' => $owner->getKey(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $other = Customer::query()->findOrFail($otherId);

        app(SalePostingService::class)->post(
            $owner, $customer, Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-11-01'), '100.00', 'Lotiya sale', 'village-filter-sale-one',
        );
        app(SalePostingService::class)->post(
            $owner, $other, Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-11-01'), '200.00', 'Biaora sale', 'village-filter-sale-two',
        );
        app(PaymentPostingService::class)->post(
            $owner, $customer, '20.00', CarbonImmutable::parse('2026-11-02'),
            'CASH', null, 'Lotiya payment', 'village-filter-payment-one',
        );
        app(PaymentPostingService::class)->post(
            $owner, $other, '30.00', CarbonImmutable::parse('2026-11-02'),
            'CASH', null, 'Biaora payment', 'village-filter-payment-two',
        );

        Sanctum::actingAs($owner, ['app']);

        $this->getJson('/api/v1/sales?village_code=1001')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.customer_id', $customer->getKey());
        $this->getJson('/api/v1/payments?village_code=1001')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.customer_id', $customer->getKey());
        $this->getJson('/api/v1/reports/sales?village_code=1001')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('summary.gross_sales', '100.00');
        $this->getJson('/api/v1/reports/payments?village_code=1001')->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('summary.normal_receipts', '20.00');
        $this->getJson('/api/v1/reports/outstanding?as_of=2026-11-02&village_code=1001')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.customer_id', $customer->getKey());
    }

}
