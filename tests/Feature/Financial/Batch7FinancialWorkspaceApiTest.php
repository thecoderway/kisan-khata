<?php

namespace Tests\Feature\Financial;

use App\Models\Customer;
use App\Models\Shop;
use App\Models\User;
use App\Modules\Financial\Application\PaymentPostingService;
use App\Modules\Financial\Application\SalePostingService;
use App\Tenancy\CurrentShop;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Batch7FinancialWorkspaceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_all_customer_ledgers_inside_own_shop_but_not_other_shops(): void
    {
        [$shop, $owner, $first] = $this->context();
        $secondId = DB::table('customers')->insertGetId([
            'shop_id' => $shop->getKey(), 'name_original' => 'Second customer',
            'name_original_language' => 'en', 'name_en' => 'Second customer',
            'status' => Customer::STATUS_ACTIVE, 'created_by' => $owner->getKey(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        [, , $foreign] = $this->context();

        $this->createPolicy($shop, $owner);
        $sale = app(SalePostingService::class)->post(
            $owner, $first, 'UNPAID', CarbonImmutable::parse('2026-11-20'),
            '100.00', 'Shop-wide Staff read test', 'batch-7-staff-sale',
        );
        $payment = app(PaymentPostingService::class)->post(
            $owner, $first, '20.00', CarbonImmutable::parse('2026-11-21'),
            'CASH', null, 'Shop-wide Staff read test', 'batch-7-staff-payment',
        );

        $staff = User::factory()->create([
            'shop_id' => $shop->getKey(), 'role' => User::ROLE_STAFF, 'is_active' => true,
        ]);
        Sanctum::actingAs($staff, ['app']);

        $this->getJson('/api/v1/customers/'.$first->getKey().'/ledger')->assertOk()
            ->assertJsonFragment(['sale_id' => $sale->getKey()]);
        $this->getJson('/api/v1/customers/'.$secondId.'/ledger')->assertOk();
        $this->getJson('/api/v1/customers/'.$foreign->getKey().'/ledger')->assertNotFound();
        $this->getJson('/api/v1/sales')->assertOk()->assertJsonFragment(['sale_number' => $sale->sale_number]);
        $this->getJson('/api/v1/payments')->assertOk()->assertJsonFragment(['payment_number' => $payment->payment_number]);
    }

    public function test_owner_can_create_effective_dated_shop_policy_and_audit_event(): void
    {
        [$shop, $owner] = $this->context();
        $this->createPolicy($shop, $owner);
        Sanctum::actingAs($owner, ['app']);

        $effectiveFrom = now()->addDays(5)->toDateString();
        $this->postJson('/api/v1/interest-policy', [
            'effective_from' => $effectiveFrom,
            'monthly_rate_percent' => '1.25000000',
            'grace_days' => 30,
            'capitalization_enabled' => false,
            'payment_waterfall' => 'PRINCIPAL_FIRST',
            'annual_settlement_month_1' => 3,
            'annual_settlement_day_1' => 30,
            'annual_settlement_month_2' => 10,
            'annual_settlement_day_2' => 30,
        ])->assertCreated()->assertJsonPath('data.version_number', 2);

        $this->assertDatabaseHas('interest_policy_versions', [
            'shop_id' => $shop->getKey(), 'version_number' => 1, 'effective_to' => $effectiveFrom,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'shop_id' => $shop->getKey(), 'user_id' => $owner->getKey(),
            'action' => 'financial.shop_interest_policy_updated',
        ]);
    }

    public function test_staff_cannot_change_interest_policy_or_view_financial_audit(): void
    {
        [$shop, $owner] = $this->context();
        $this->createPolicy($shop, $owner);
        $staff = User::factory()->create([
            'shop_id' => $shop->getKey(), 'role' => User::ROLE_STAFF, 'is_active' => true,
        ]);
        Sanctum::actingAs($staff, ['app']);

        $this->getJson('/api/v1/interest-policy')->assertForbidden();
        $this->postJson('/api/v1/interest-policy', [])->assertForbidden();
        $this->getJson('/api/v1/financial-audit')->assertForbidden();
    }

    public function test_owner_projection_repair_requires_reason_and_writes_audit_in_same_operation(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);
        Sanctum::actingAs($owner, ['app']);

        app(SalePostingService::class)->post(
            $owner, $customer, 'UNPAID', CarbonImmutable::parse('2026-11-01'),
            '100.00', 'Batch 7 repair test', 'batch-7-repair-test',
        );
        DB::table('customer_accounts')->where('shop_id', $shop->getKey())
            ->where('customer_id', $customer->getKey())
            ->update(['principal_balance' => '90.00', 'total_payable' => '90.00']);

        $this->postJson('/api/v1/reconciliation/customers/'.$customer->getKey().'/repair', [])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->postJson('/api/v1/reconciliation/customers/'.$customer->getKey().'/repair', [
            'reason' => 'Repair verified projection mismatch',
        ])->assertOk()->assertJsonPath('data.consistent', true);

        $this->assertDatabaseHas('customer_accounts', [
            'shop_id' => $shop->getKey(), 'customer_id' => $customer->getKey(),
            'principal_balance' => '100.00', 'total_payable' => '100.00',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'shop_id' => $shop->getKey(), 'user_id' => $owner->getKey(),
            'action' => 'financial.customer_projection_repaired',
            'entity_type' => 'customer_projection', 'entity_id' => $customer->getKey(),
            'reason' => 'Repair verified projection mismatch',
        ]);
    }

    private function context(): array
    {
        $shop = Shop::factory()->create(['timezone' => 'Asia/Kolkata']);
        $owner = User::factory()->create([
            'shop_id' => $shop->getKey(), 'role' => User::ROLE_OWNER, 'is_active' => true,
        ]);
        app(CurrentShop::class)->set($shop);
        $id = DB::table('customers')->insertGetId([
            'shop_id' => $shop->getKey(), 'name_original' => 'Batch Seven Customer',
            'name_original_language' => 'en', 'name_en' => 'Batch Seven Customer',
            'status' => Customer::STATUS_ACTIVE, 'created_by' => $owner->getKey(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$shop, $owner, Customer::query()->findOrFail($id)];
    }

    private function createPolicy(Shop $shop, User $owner): void
    {
        $policyId = DB::table('interest_policies')->insertGetId([
            'shop_id' => $shop->getKey(), 'policy_code' => 'DEFAULT',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('interest_policy_versions')->insert([
            'shop_id' => $shop->getKey(), 'interest_policy_id' => $policyId,
            'version_number' => 1, 'effective_from' => '2026-01-01', 'effective_to' => null,
            'monthly_rate_percent' => '0.00000000', 'grace_days' => 30,
            'capitalization_enabled' => false, 'payment_waterfall' => 'PRINCIPAL_FIRST',
            'annual_settlement_month_1' => 3, 'annual_settlement_day_1' => 30,
            'annual_settlement_month_2' => 10, 'annual_settlement_day_2' => 30,
            'created_by' => $owner->getKey(), 'created_at' => now(),
        ]);
    }
}
