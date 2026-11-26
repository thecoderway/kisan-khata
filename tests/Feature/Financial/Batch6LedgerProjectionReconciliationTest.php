<?php

namespace Tests\Feature\Financial;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Modules\Financial\Application\PaymentPostingService;
use App\Modules\Financial\Application\ReconciliationService;
use App\Modules\Financial\Application\SalePostingService;
use App\Tenancy\CurrentShop;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Batch6LedgerProjectionReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_and_payment_postings_keep_account_projection_and_snapshots_reconciled(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);

        $sale = app(SalePostingService::class)->post(
            $owner,
            $customer,
            Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-11-01'),
            '100.00',
            'Batch 6 projection sale',
            'batch-6-sale-001',
        );

        $account = CustomerAccount::query()->where('customer_id', $customer->getKey())->firstOrFail();
        $this->assertSame('100.00', $account->principal_balance);
        $this->assertSame('100.00', $account->total_payable);
        $this->assertDatabaseHas('account_balance_snapshots', [
            'shop_id' => $shop->getKey(),
            'customer_id' => $customer->getKey(),
            'as_of_date' => '2026-11-01',
            'principal_balance' => '100.00',
            'interest_balance' => '0.00',
            'total_payable' => '100.00',
        ]);

        $payment = app(PaymentPostingService::class)->post(
            $owner,
            $customer,
            '40.00',
            CarbonImmutable::parse('2026-11-10'),
            'CASH',
            null,
            'Batch 6 projection payment',
            'batch-6-payment-001',
        );

        $this->assertNotNull($payment->getKey());
        $this->assertSame('60.00', $account->fresh()->principal_balance);
        $this->assertSame('60.00', $account->fresh()->total_payable);
        $this->assertSame(Sale::PAYMENT_STATUS_PARTIALLY_PAID, $sale->fresh()->payment_status);

        $this->assertSame('2026-11-10', $account->fresh()->last_interest_cutoff_date->toDateString());

        $result = app(ReconciliationService::class)->reconcileCustomer($customer);
        $this->assertTrue($result['consistent'], json_encode($result['discrepancies']));
        $this->assertSame([], $result['discrepancies']);

        DB::table('customer_accounts')->where('id', $account->getKey())->update([
            'last_interest_cutoff_date' => '2026-11-09',
        ]);
        $checkpointReport = app(ReconciliationService::class)->reconcileCustomer($customer);
        $this->assertContains('interest_checkpoint_mismatch', array_column($checkpointReport['discrepancies'], 'type'));

        $checkpointRepair = app(ReconciliationService::class)->rebuildAndReconcile($customer);
        $this->assertTrue($checkpointRepair['consistent'], json_encode($checkpointRepair['discrepancies']));
        $this->assertSame('2026-11-10', $account->fresh()->last_interest_cutoff_date->toDateString());
    }

    public function test_sale_day_auto_payment_remains_reconciled_without_fifo_allocation_records(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);

        $sale = app(SalePostingService::class)->post(
            $owner,
            $customer,
            Sale::PAYMENT_CHOICE_PAID,
            CarbonImmutable::parse('2026-11-01'),
            '100.00',
            'Sale-day auto payment projection',
            'batch-6-sale-day-auto',
        );

        $account = CustomerAccount::query()->where('customer_id', $customer->getKey())->firstOrFail();

        $this->assertSame('0.00', $account->principal_balance);
        $this->assertSame('0.00', $account->total_payable);
        $this->assertSame(Sale::PAYMENT_STATUS_PAID, $sale->fresh()->payment_status);
        $this->assertDatabaseCount('payment_allocations', 0);

        $report = app(ReconciliationService::class)->reconcileCustomer($customer);
        $this->assertTrue($report['consistent'], json_encode($report['discrepancies']));
    }

    public function test_reconciliation_is_read_only_and_explicit_rebuild_repairs_projection_snapshot_and_sale_status(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);

        $sale = app(SalePostingService::class)->post(
            $owner,
            $customer,
            Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-11-01'),
            '100.00',
            'Reconciliation repair sale',
            'batch-6-repair-sale',
        );

        $account = CustomerAccount::query()->where('customer_id', $customer->getKey())->firstOrFail();
        $snapshot = DB::table('account_balance_snapshots')
            ->where('shop_id', $shop->getKey())
            ->where('customer_id', $customer->getKey())
            ->where('as_of_date', '2026-11-01')
            ->first();
        $this->assertNotNull($snapshot);

        DB::table('customer_accounts')->where('id', $account->getKey())->update([
            'principal_balance' => '77.00',
            'total_payable' => '77.00',
        ]);
        DB::table('account_balance_snapshots')->where('id', $snapshot->id)->update([
            'principal_balance' => '66.00',
            'total_payable' => '66.00',
        ]);
        DB::table('sales')->where('id', $sale->getKey())->update([
            'payment_status' => Sale::PAYMENT_STATUS_PAID,
        ]);

        $beforeAccount = DB::table('customer_accounts')->where('id', $account->getKey())->first();
        $beforeSnapshot = DB::table('account_balance_snapshots')->where('id', $snapshot->id)->first();
        $beforeSaleStatus = DB::table('sales')->where('id', $sale->getKey())->value('payment_status');

        $report = app(ReconciliationService::class)->reconcileCustomer($customer);

        $this->assertFalse($report['consistent']);
        $this->assertContains('account_projection_mismatch', array_column($report['discrepancies'], 'type'));
        $this->assertContains('balance_snapshot_mismatch', array_column($report['discrepancies'], 'type'));
        $this->assertContains('sale_payment_status_mismatch', array_column($report['discrepancies'], 'type'));

        $this->assertSame($beforeAccount->principal_balance, DB::table('customer_accounts')->where('id', $account->getKey())->value('principal_balance'));
        $this->assertSame($beforeSnapshot->principal_balance, DB::table('account_balance_snapshots')->where('id', $snapshot->id)->value('principal_balance'));
        $this->assertSame($beforeSaleStatus, DB::table('sales')->where('id', $sale->getKey())->value('payment_status'));

        $repaired = app(ReconciliationService::class)->rebuildAndReconcile($customer);

        $this->assertTrue($repaired['consistent'], json_encode($repaired['discrepancies']));
        $this->assertSame('100.00', $account->fresh()->principal_balance);
        $this->assertSame(Sale::PAYMENT_STATUS_UNPAID, $sale->fresh()->payment_status);
        $this->assertSame('100.00', DB::table('account_balance_snapshots')->where('id', $snapshot->id)->value('principal_balance'));
    }

    public function test_reconciliation_detects_mismatched_payment_allocation_without_repairing_it(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);

        app(SalePostingService::class)->post(
            $owner,
            $customer,
            Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-11-01'),
            '100.00',
            'Allocation integrity sale',
            'batch-6-allocation-sale',
        );

        $payment = app(PaymentPostingService::class)->post(
            $owner,
            $customer,
            '40.00',
            CarbonImmutable::parse('2026-11-10'),
            'CASH',
            null,
            'Allocation integrity payment',
            'batch-6-allocation-payment',
        );

        DB::table('payment_allocations')
            ->where('shop_id', $shop->getKey())
            ->where('payment_id', $payment->getKey())
            ->update(['total_allocated' => '39.00']);

        $report = app(ReconciliationService::class)->reconcileCustomer($customer);

        $this->assertFalse($report['consistent']);
        $this->assertContains('allocation_header_mismatch', array_column($report['discrepancies'], 'type'));
        $this->assertSame('39.00', DB::table('payment_allocations')
            ->where('payment_id', $payment->getKey())
            ->value('total_allocated'));
    }

    public function test_reconciliation_rejects_customer_from_another_shop(): void
    {
        [$shopA] = $this->context();
        [, , $customerB] = $this->context();

        app(CurrentShop::class)->set($shopA);

        $this->expectException(AuthorizationException::class);
        app(ReconciliationService::class)->reconcileCustomer($customerB);
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
            'name_original' => 'Batch Six Customer',
            'name_original_language' => 'en',
            'name_en' => 'Batch Six Customer',
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
}
