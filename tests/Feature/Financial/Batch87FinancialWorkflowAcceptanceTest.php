<?php

namespace Tests\Feature\Financial;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\Payment;
use App\Models\PaymentAllocationItem;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Modules\Financial\Application\AnnualInterestSettlementService;
use App\Modules\Financial\Application\FinancialAuditService;
use App\Modules\Financial\Application\PaymentPostingService;
use App\Modules\Financial\Application\PaymentReversalService;
use App\Modules\Financial\Application\ReconciliationService;
use App\Modules\Financial\Application\SalePostingService;
use App\Tenancy\CurrentShop;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class Batch87FinancialWorkflowAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_payment_annual_settlement_and_ledger_reconcile_as_one_workflow(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner, '3.00000000', 0);

        $unpaidSale = app(SalePostingService::class)->post(
            $owner,
            $customer,
            Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-10-01'),
            '1000.00',
            'E2E unpaid sale',
            'batch-8-7-e2e-unpaid-sale',
        );

        $paidSale = app(SalePostingService::class)->post(
            $owner,
            $customer,
            Sale::PAYMENT_CHOICE_PAID,
            CarbonImmutable::parse('2026-10-01'),
            '250.00',
            'E2E paid sale',
            'batch-8-7-e2e-paid-sale',
        );

        $this->assertSame(Sale::PAYMENT_STATUS_PAID, $paidSale->fresh()->payment_status);
        $this->assertDatabaseHas('payments', [
            'source_sale_id' => $paidSale->getKey(),
            'payment_type' => Payment::TYPE_SALE_DAY_AUTO,
            'amount' => '250.00',
        ]);

        $payment = app(PaymentPostingService::class)->post(
            $owner,
            $customer,
            '100.00',
            CarbonImmutable::parse('2026-10-30'),
            'CASH',
            'E2E-RECEIPT-001',
            'E2E normal payment',
            'batch-8-7-e2e-normal-payment',
        );

        $allocationItems = PaymentAllocationItem::query()
            ->where('payment_id', $payment->getKey())
            ->get();

        $this->assertNotEmpty($allocationItems);
        $this->assertSame(
            [$unpaidSale->getKey()],
            $allocationItems->pluck('sale_id')->unique()->map(fn ($id) => (int) $id)->values()->all(),
            'FIFO allocation must exclude the already-paid sale.',
        );
        $this->assertSame('100.00', BigDecimal::of((string) $allocationItems->sum('amount'))->toScale(2)->__toString());

        $settlement = app(AnnualInterestSettlementService::class)
            ->settleShopDate($shop, '2026-10-30');

        $this->assertSame(1, $settlement['completed']);
        $this->assertDatabaseCount('interest_settlement_runs', 1);

        $account = CustomerAccount::query()
            ->where('customer_id', $customer->getKey())
            ->firstOrFail();

        $this->assertSame('900.00', $account->principal_balance);
        $this->assertSame('28.00', $account->interest_balance);
        $this->assertSame('928.00', $account->total_payable);

        $reconciliation = app(ReconciliationService::class)->reconcileCustomer($customer);

        $this->assertTrue($reconciliation['consistent'], json_encode($reconciliation['discrepancies']));
        $this->assertDatabaseHas('ledger_entries', [
            'sale_id' => $unpaidSale->getKey(),
            'entry_kind' => 'SALE',
            'debit_amount' => '1000.00',
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'payment_id' => $payment->getKey(),
            'entry_kind' => 'PAYMENT',
            'credit_amount' => '100.00',
        ]);
    }

    public function test_paid_sale_and_automatic_payment_roll_back_together_when_auto_payment_audit_fails(): void
    {
        [, $owner, $customer] = $this->context();

        $calls = 0;
        $realAudit = app(FinancialAuditService::class);
        $audit = Mockery::mock(FinancialAuditService::class);
        $audit->shouldReceive('recordTransaction')
            ->twice()
            ->andReturnUsing(function (...$arguments) use (&$calls, $realAudit) {
                $calls++;
                if ($calls === 2) {
                    throw new RuntimeException('Simulated automatic-payment audit failure.');
                }

                return $realAudit->recordTransaction(...$arguments);
            });
        $this->app->instance(FinancialAuditService::class, $audit);

        try {
            app(SalePostingService::class)->post(
                $owner,
                $customer,
                Sale::PAYMENT_CHOICE_PAID,
                CarbonImmutable::parse('2026-11-01'),
                '500.00',
                'Rollback regression',
                'batch-8-7-paid-sale-rollback',
            );
            $this->fail('The simulated audit failure must abort the Paid Sale.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated automatic-payment audit failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertDatabaseCount('idempotency_keys', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('customer_accounts', 0);
    }

    public function test_payment_allocation_and_interest_checkpoint_roll_back_when_audit_fails(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner, '0.00000000', 0);

        $sale = app(SalePostingService::class)->post(
            $owner,
            $customer,
            Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-11-01'),
            '1000.00',
            'Payment rollback fixture',
            'batch-8-7-payment-rollback-sale',
        );

        $audit = Mockery::mock(FinancialAuditService::class);
        $audit->shouldReceive('recordTransaction')
            ->once()
            ->andThrow(new RuntimeException('Simulated payment audit failure.'));
        $this->app->instance(FinancialAuditService::class, $audit);

        try {
            app(PaymentPostingService::class)->post(
                $owner,
                $customer,
                '100.00',
                CarbonImmutable::parse('2026-11-10'),
                'CASH',
                null,
                'Payment rollback regression',
                'batch-8-7-payment-rollback',
            );
            $this->fail('The simulated audit failure must abort the Payment.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated payment audit failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_allocations', 0);
        $this->assertDatabaseCount('payment_allocation_items', 0);
        $this->assertDatabaseCount('interest_calculation_runs', 0);
        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('ledger_entries', [
            'sale_id' => $sale->getKey(),
            'entry_kind' => 'SALE',
        ]);

        $account = CustomerAccount::query()->where('customer_id', $customer->getKey())->firstOrFail();
        $this->assertSame('1000.00', $account->total_payable);
    }

    public function test_payment_correction_api_is_owner_only_and_hides_cross_shop_payment(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner, '0.00000000', 30);
        app(SalePostingService::class)->post(
            $owner,
            $customer,
            Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-11-01'),
            '100.00',
            'Correction API fixture',
            'batch-8-7-correction-sale',
        );
        $payment = app(PaymentPostingService::class)->post(
            $owner,
            $customer,
            '20.00',
            CarbonImmutable::parse('2026-11-02'),
            'CASH',
            null,
            'Correction API fixture',
            'batch-8-7-correction-payment',
        );

        [$foreignShop, $foreignOwner, $foreignCustomer] = $this->context();
        $this->createPolicy($foreignShop, $foreignOwner, '0.00000000', 30);
        app(SalePostingService::class)->post(
            $foreignOwner,
            $foreignCustomer,
            Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-11-01'),
            '100.00',
            'Foreign shop fixture',
            'batch-8-7-foreign-sale',
        );
        $foreignPayment = app(PaymentPostingService::class)->post(
            $foreignOwner,
            $foreignCustomer,
            '10.00',
            CarbonImmutable::parse('2026-11-02'),
            'CASH',
            null,
            'Foreign shop fixture',
            'batch-8-7-foreign-payment',
        );

        app(CurrentShop::class)->set($shop);
        $manager = User::factory()->create([
            'shop_id' => $shop->getKey(),
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);
        $staff = User::factory()->create([
            'shop_id' => $shop->getKey(),
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);
        $url = '/api/v1/customers/'.$customer->getKey().'/payments/'.$payment->getKey().'/correction';
        $payload = ['reason' => 'API authorization regression'];

        Sanctum::actingAs($manager, ['app']);
        $this->postJson($url, $payload, ['Idempotency-Key' => 'batch-8-7-manager-correction'])
            ->assertForbidden();

        Sanctum::actingAs($staff, ['app']);
        $this->postJson($url, $payload, ['Idempotency-Key' => 'batch-8-7-staff-correction'])
            ->assertForbidden();

        Sanctum::actingAs($owner, ['app']);
        $this->postJson(
            '/api/v1/customers/'.$foreignCustomer->getKey().'/payments/'.$foreignPayment->getKey().'/correction',
            $payload,
            ['Idempotency-Key' => 'batch-8-7-cross-shop-correction'],
        )->assertNotFound();

        $this->postJson($url, $payload, ['Idempotency-Key' => 'batch-8-7-owner-correction'])
            ->assertCreated();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->getKey(),
            'payment_type' => Payment::TYPE_NORMAL,
        ]);
        $this->assertDatabaseHas('payments', [
            'reversal_of_payment_id' => $payment->getKey(),
            'payment_type' => Payment::TYPE_REVERSAL,
        ]);
    }

    public function test_combined_sale_partial_payments_reversal_replacement_and_audit_reconcile(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner, '0.00000000', 30);

        $sale = app(SalePostingService::class)->post(
            $owner,
            $customer,
            Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-11-01'),
            '10000.00',
            'Batch 7C combined lifecycle Sale',
            'batch-7c-combined-sale',
        );

        $firstPayment = app(PaymentPostingService::class)->post(
            $owner,
            $customer,
            '4000.00',
            CarbonImmutable::parse('2026-11-02'),
            'CASH',
            'BATCH-7C-001',
            'First partial Payment',
            'batch-7c-first-payment',
        );

        $secondPayment = app(PaymentPostingService::class)->post(
            $owner,
            $customer,
            '2000.00',
            CarbonImmutable::parse('2026-11-03'),
            'CASH',
            'BATCH-7C-002',
            'Second partial Payment',
            'batch-7c-second-payment',
        );

        $this->assertSame('10000.00', $sale->fresh()->amount);
        $this->assertSame(Sale::PAYMENT_STATUS_PARTIALLY_PAID, $sale->fresh()->payment_status);
        $this->assertSame('4000.00', CustomerAccount::query()->where('customer_id', $customer->getKey())->value('total_payable'));

        $correction = app(PaymentReversalService::class)->correct(
            $owner,
            $customer,
            $secondPayment,
            'Batch 7C controlled correction with replacement',
            'batch-7c-reversal-replacement',
            [
                'amount' => '2000.00',
                'payment_method' => 'UPI',
                'reference_number' => 'BATCH-7C-REPLACEMENT',
                'description' => 'Replacement for corrected Payment',
            ],
        );

        $this->assertSame(Payment::TYPE_REVERSAL, $correction['reversal']->payment_type);
        $this->assertNotNull($correction['replacement']);
        $this->assertSame('2000.00', $correction['replacement']->amount);
        $this->assertSame((int) $secondPayment->getKey(), (int) $correction['reversal']->reversal_of_payment_id);
        $this->assertSame((int) $secondPayment->getKey(), (int) $correction['replacement']->replacement_for_payment_id);

        $account = CustomerAccount::query()
            ->where('customer_id', $customer->getKey())
            ->firstOrFail();
        $this->assertSame('4000.00', $account->fresh()->principal_balance);
        $this->assertSame('0.00', $account->fresh()->interest_balance);
        $this->assertSame('4000.00', $account->fresh()->total_payable);
        $this->assertSame(Sale::PAYMENT_STATUS_PARTIALLY_PAID, $sale->fresh()->payment_status);

        $debits = (string) DB::table('ledger_entries')->where('customer_id', $customer->getKey())->sum('debit_amount');
        $credits = (string) DB::table('ledger_entries')->where('customer_id', $customer->getKey())->sum('credit_amount');
        $this->assertSame('12000.00', BigDecimal::of($debits)->toScale(2)->__toString());
        $this->assertSame('8000.00', BigDecimal::of($credits)->toScale(2)->__toString());
        $this->assertSame('4000.00', BigDecimal::of($debits)->minus($credits)->toScale(2)->__toString());

        $audit = DB::table('audit_logs')
            ->where('shop_id', $shop->getKey())
            ->where('user_id', $owner->getKey())
            ->where('action', 'financial.payment_reversed')
            ->where('reason', 'Batch 7C controlled correction with replacement')
            ->first();
        $this->assertNotNull($audit);

        $reconciliation = app(ReconciliationService::class)->reconcileCustomer($customer);
        $this->assertTrue($reconciliation['consistent'], json_encode($reconciliation['discrepancies']));

        Sanctum::actingAs($owner, ['app']);
        $this->getJson('/api/v1/payments/'.$secondPayment->getKey())
            ->assertOk()
            ->assertJsonPath('data.revisions.0.revision_type', 'REVERSAL')
            ->assertJsonPath('data.revisions.0.correction_reason', 'Batch 7C controlled correction with replacement')
            ->assertJsonPath('data.revisions.1.revision_type', 'REPLACEMENT')

        $this->assertSame('4000.00', $firstPayment->fresh()->amount);
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
            'name_original' => 'Batch 8.7 Customer',
            'name_original_language' => 'en',
            'name_en' => 'Batch 8.7 Customer',
            'status' => Customer::STATUS_ACTIVE,
            'created_by' => $owner->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$shop, $owner, Customer::query()->findOrFail($customerId)];
    }

    private function createPolicy(Shop $shop, User $owner, string $rate, int $graceDays): void
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
            'grace_days' => $graceDays,
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
