<?php

namespace Tests\Feature\Financial;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\Payment;
use App\Models\PaymentAllocationItem;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Modules\Financial\Application\PaymentPostingService;
use App\Modules\Financial\Application\PaymentReversalService;
use App\Modules\Financial\Application\ReconciliationService;
use App\Modules\Financial\Application\SalePostingService;
use App\Tenancy\CurrentShop;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class PaymentPostingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_payment_allocates_oldest_sales_first_and_replays_idempotently(): void
    {
        [$shop, $owner, $customer] = $this->makeCustomerContext();
        $this->createPolicy($shop, $owner, '0.00000000', 'PRINCIPAL_FIRST', 30);

        $first = $this->postSale($owner, $customer, '2026-11-01', '100.00', 'fifo-sale-1');
        $second = $this->postSale($owner, $customer, '2026-11-02', '200.00', 'fifo-sale-2');

        $service = app(PaymentPostingService::class);
        $payment = $service->post(
            $owner, $customer, '150.00', CarbonImmutable::parse('2026-11-10'),
            'CASH', null, 'FIFO payment', 'fifo-payment-001',
        );
        $replayed = $service->post(
            $owner, $customer, '150.00', CarbonImmutable::parse('2026-11-10'),
            'CASH', null, 'FIFO payment', 'fifo-payment-001',
        );

        $this->assertSame($payment->getKey(), $replayed->getKey());
        $this->assertSame(Payment::TYPE_NORMAL, $payment->payment_type);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('payment_allocations', 1);
        $this->assertDatabaseCount('payment_allocation_items', 2);

        $items = PaymentAllocationItem::query()->orderBy('sale_fifo_sequence')->get();
        $this->assertSame((int) $first->getKey(), (int) $items[0]->sale_id);
        $this->assertSame('100.00', $items[0]->amount);
        $this->assertSame((int) $second->getKey(), (int) $items[1]->sale_id);
        $this->assertSame('50.00', $items[1]->amount);
        $this->assertSame(Sale::PAYMENT_STATUS_PAID, $first->fresh()->payment_status);
        $this->assertSame(Sale::PAYMENT_STATUS_PARTIALLY_PAID, $second->fresh()->payment_status);

        $account = CustomerAccount::query()->where('customer_id', $customer->getKey())->firstOrFail();
        $this->assertSame('150.00', $account->principal_balance);
        $this->assertSame('0.00', $account->interest_balance);
        $this->assertSame('150.00', $account->total_payable);
    }

    public function test_sale_day_auto_paid_sales_are_excluded_from_normal_fifo(): void
    {
        [$shop, $owner, $customer] = $this->makeCustomerContext();
        $this->createPolicy($shop, $owner, '0.00000000', 'PRINCIPAL_FIRST', 30);
        $paidSale = app(SalePostingService::class)->post(
            $owner, $customer, Sale::PAYMENT_CHOICE_PAID,
            CarbonImmutable::parse('2026-11-01'), '100.00', 'Already paid', 'sale-day-auto-paid',
        );
        $unpaidSale = $this->postSale($owner, $customer, '2026-11-02', '200.00', 'fifo-after-auto-sale');

        $payment = app(PaymentPostingService::class)->post(
            $owner, $customer, '150.00', CarbonImmutable::parse('2026-11-10'),
            null, null, null, 'fifo-excludes-auto-paid',
        );

        $items = PaymentAllocationItem::query()->where('payment_id', $payment->getKey())->get();
        $this->assertCount(1, $items);
        $this->assertSame((int) $unpaidSale->getKey(), (int) $items[0]->sale_id);
        $this->assertNotSame((int) $paidSale->getKey(), (int) $items[0]->sale_id);
        $this->assertSame(Sale::PAYMENT_STATUS_PAID, $paidSale->fresh()->payment_status);
        $this->assertSame('150.00', $items[0]->amount);
    }

    public function test_principal_first_applies_payment_to_sale_principal_before_interest(): void
    {
        [$shop, $owner, $customer] = $this->makeCustomerContext();
        $this->createPolicy($shop, $owner, '3.00000000', 'PRINCIPAL_FIRST', 0);
        $sale = $this->postSale($owner, $customer, '2026-11-01', '1000.00', 'principal-first-sale');

        $payment = app(PaymentPostingService::class)->post(
            $owner, $customer, '5.00', CarbonImmutable::parse('2026-11-11'),
            null, null, null, 'principal-first-payment',
        );

        $item = PaymentAllocationItem::query()->where('payment_id', $payment->getKey())->firstOrFail();
        $this->assertSame(PaymentAllocationItem::COMPONENT_PRINCIPAL, $item->component_type);
        $this->assertSame((int) $sale->getKey(), (int) $item->sale_id);
        $account = CustomerAccount::query()->where('customer_id', $customer->getKey())->firstOrFail();
        $this->assertSame('995.00', $account->principal_balance);
        $this->assertSame('9.00', $account->interest_balance);
        $this->assertSame('1004.00', $account->total_payable);
    }

    public function test_interest_first_allocates_to_oldest_non_capitalized_interest_lot_before_principal(): void
    {
        [$shop, $owner, $customer] = $this->makeCustomerContext();
        $this->createPolicy($shop, $owner, '3.00000000', 'INTEREST_FIRST', 0);
        $sale = $this->postSale($owner, $customer, '2026-11-01', '1000.00', 'interest-first-sale');

        $payment = app(PaymentPostingService::class)->post(
            $owner, $customer, '9.00', CarbonImmutable::parse('2026-11-11'),
            null, null, null, 'interest-first-payment',
        );

        $item = PaymentAllocationItem::query()->where('payment_id', $payment->getKey())->firstOrFail();
        $this->assertSame(PaymentAllocationItem::COMPONENT_NON_CAPITALIZED_INTEREST, $item->component_type);
        $this->assertSame((int) $sale->getKey(), (int) $item->sale_id);
        $this->assertNotNull($item->source_interest_lot_id);
        $this->assertDatabaseHas('interest_lot_events', [
            'interest_lot_id' => $item->source_interest_lot_id,
            'event_type' => 'ALLOCATED',
            'allocation_item_id' => $item->getKey(),
            'amount' => '9.00',
        ]);

        $account = CustomerAccount::query()->where('customer_id', $customer->getKey())->firstOrFail();
        $this->assertSame('1000.00', $account->principal_balance);
        $this->assertSame('0.00', $account->interest_balance);
        $this->assertSame('1000.00', $account->total_payable);
    }

    public function test_capitalized_interest_is_allocated_as_principal_without_double_counting(): void
    {
        [$shop, $owner, $customer] = $this->makeCustomerContext();
        $this->createPolicy($shop, $owner, '3.00000000', 'PRINCIPAL_FIRST', 0);
        DB::table('interest_policy_versions')
            ->where('shop_id', $shop->getKey())
            ->update(['capitalization_enabled' => true]);
        $sale = $this->postSale($owner, $customer, '2026-11-01', '1000.00', 'capitalized-interest-sale');

        $payment = app(PaymentPostingService::class)->post(
            $owner, $customer, '1005.00', CarbonImmutable::parse('2026-11-11'),
            null, null, null, 'capitalized-interest-payment',
        );

        $items = PaymentAllocationItem::query()
            ->where('payment_id', $payment->getKey())
            ->orderBy('component_sequence')
            ->get();
        $this->assertCount(2, $items);
        $this->assertSame(PaymentAllocationItem::COMPONENT_PRINCIPAL, $items[0]->component_type);
        $this->assertSame('1000.00', $items[0]->amount);
        $this->assertSame(PaymentAllocationItem::COMPONENT_CAPITALIZED_INTEREST_PRINCIPAL, $items[1]->component_type);
        $this->assertSame('5.00', $items[1]->amount);
        $this->assertSame((int) $sale->getKey(), (int) $items[1]->sale_id);

        $account = CustomerAccount::query()->where('customer_id', $customer->getKey())->firstOrFail();
        $this->assertSame('4.00', $account->principal_balance);
        $this->assertSame('0.00', $account->interest_balance);
        $this->assertSame('4.00', $account->total_payable);
    }

    public function test_excess_payment_is_rejected_and_entire_operation_rolls_back(): void
    {
        [$shop, $owner, $customer] = $this->makeCustomerContext();
        $this->createPolicy($shop, $owner, '0.00000000', 'PRINCIPAL_FIRST', 30);
        $this->postSale($owner, $customer, '2026-11-01', '100.00', 'excess-payment-sale');

        try {
            app(PaymentPostingService::class)->post(
                $owner, $customer, '100.01', CarbonImmutable::parse('2026-11-10'),
                null, null, null, 'excess-payment-001',
            );
            $this->fail('Excess payments must be rejected.');
        } catch (ConflictHttpException) {
            $this->assertDatabaseCount('payments', 0);
            $this->assertDatabaseCount('payment_allocations', 0);
            $this->assertDatabaseCount('payment_allocation_items', 0);
            $this->assertDatabaseCount('interest_calculation_runs', 0);
            $this->assertDatabaseMissing('idempotency_keys', [
                'operation_type' => 'PAYMENT_POSTING',
                'key_hash' => hash('sha256', 'excess-payment-001'),
            ]);
            $this->assertSame('100.00', CustomerAccount::query()->where('customer_id', $customer->getKey())->firstOrFail()->total_payable);

        }
    }

    public function test_invalid_amounts_are_rejected_before_any_financial_write(): void
    {
        [, $owner, $customer] = $this->makeCustomerContext();

        foreach (['0', '-1', '10.001', '12345678901234'] as $amount) {
            try {
                app(PaymentPostingService::class)->post(
                    $owner, $customer, $amount, CarbonImmutable::parse('2026-11-10'),
                    null, null, null, 'invalid-payment-'.hash('sha256', $amount),
                );
                $this->fail('Invalid Payment amount must be rejected.');
            } catch (\InvalidArgumentException) {
                $this->assertDatabaseCount('payments', 0);
                $this->assertDatabaseCount('financial_transactions', 0);
            }
        }
    }

    public function test_owner_can_reverse_a_payment_without_mutating_original_financial_records(): void
    {
        [$shop, $owner, $customer] = $this->makeCustomerContext();
        $this->createPolicy($shop, $owner, '0.00000000', 'PRINCIPAL_FIRST', 30);
        $sale = $this->postSale($owner, $customer, '2026-11-01', '100.00', 'reversal-sale-001');
        $payment = app(PaymentPostingService::class)->post(
            $owner, $customer, '40.00', CarbonImmutable::parse('2026-11-10'),
            'CASH', 'ORIGINAL-40', 'Original receipt', 'reversal-payment-001',
        );

        $service = app(PaymentReversalService::class);
        $result = $service->correct($owner, $customer, $payment, 'Wrong amount recorded', 'payment-correction-001');
        $replayed = $service->correct($owner, $customer, $payment, 'Wrong amount recorded', 'payment-correction-001');

        $this->assertSame($result['reversal']->getKey(), $replayed['reversal']->getKey());
        $this->assertNull($result['replacement']);
        $this->assertSame(Payment::TYPE_NORMAL, $payment->fresh()->payment_type);
        $this->assertSame(Payment::TYPE_REVERSAL, $result['reversal']->payment_type);
        $this->assertSame((int) $payment->getKey(), (int) $result['reversal']->reversal_of_payment_id);
        $this->assertDatabaseCount('payment_allocations', 2);
        $this->assertDatabaseCount('payment_allocation_items', 2);
        $this->assertDatabaseHas('payment_revisions', [
            'payment_id' => $payment->getKey(),
            'revision_type' => 'REVERSAL',
            'reversal_payment_id' => $result['reversal']->getKey(),
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'payment_id' => $result['reversal']->getKey(),
            'entry_kind' => 'REVERSAL',
            'reverses_entry_id' => DB::table('ledger_entries')->where('payment_id', $payment->getKey())->value('id'),
        ]);
        $this->assertSame(Sale::PAYMENT_STATUS_UNPAID, $sale->fresh()->payment_status);
        $this->assertSame('100.00', CustomerAccount::query()->where('customer_id', $customer->getKey())->firstOrFail()->total_payable);
        $reconciliation = app(ReconciliationService::class)->reconcileCustomer($customer);
        $this->assertTrue($reconciliation['consistent'], json_encode($reconciliation['discrepancies']));
    }

    public function test_payment_reversal_can_atomically_post_a_replacement_payment(): void
    {
        [$shop, $owner, $customer] = $this->makeCustomerContext();
        $this->createPolicy($shop, $owner, '0.00000000', 'PRINCIPAL_FIRST', 30);
        $sale = $this->postSale($owner, $customer, '2026-11-01', '100.00', 'replacement-sale-001');
        $payment = app(PaymentPostingService::class)->post(
            $owner, $customer, '40.00', CarbonImmutable::parse('2026-11-10'),
            'CASH', null, 'Original receipt', 'replacement-original-001',
        );

        $result = app(PaymentReversalService::class)->correct(
            $owner,
            $customer,
            $payment,
            'Correct receipt amount',
            'replacement-correction-001',
            [
                'amount' => '70.00',
                'payment_method' => 'BANK',
                'reference_number' => 'CORRECTED-70',
                'description' => 'Replacement receipt',
            ],
        );

        $this->assertNotNull($result['replacement']);
        $this->assertSame('70.00', $result['replacement']->amount);
        $this->assertSame((int) $payment->getKey(), (int) $result['replacement']->replacement_for_payment_id);
        $this->assertSame('30.00', CustomerAccount::query()->where('customer_id', $customer->getKey())->firstOrFail()->total_payable);
        $this->assertSame(Sale::PAYMENT_STATUS_PARTIALLY_PAID, $sale->fresh()->payment_status);
        $this->assertDatabaseHas('payment_revisions', [
            'payment_id' => $payment->getKey(),
            'revision_type' => 'REPLACEMENT',
            'replacement_payment_id' => $result['replacement']->getKey(),
        ]);
    }

    public function test_reversal_restores_allocated_interest_lot_with_an_append_only_event(): void
    {
        [$shop, $owner, $customer] = $this->makeCustomerContext();
        $this->createPolicy($shop, $owner, '3.00000000', 'INTEREST_FIRST', 0);
        $this->postSale($owner, $customer, '2026-11-01', '1000.00', 'reversal-interest-sale');
        $payment = app(PaymentPostingService::class)->post(
            $owner, $customer, '9.00', CarbonImmutable::parse('2026-11-11'),
            null, null, null, 'reversal-interest-payment',
        );
        $allocationItem = PaymentAllocationItem::query()->where('payment_id', $payment->getKey())->firstOrFail();
        $this->assertNotNull($allocationItem->source_interest_lot_id);

        $result = app(PaymentReversalService::class)->correct(
            $owner, $customer, $payment, 'Restore interest allocation', 'reversal-interest-correction',
        );

        $this->assertDatabaseHas('interest_lot_events', [
            'interest_lot_id' => $allocationItem->source_interest_lot_id,
            'event_type' => 'REVERSED',
            'allocation_item_id' => DB::table('payment_allocation_items')
                ->where('payment_id', $result['reversal']->getKey())->value('id'),
            'amount' => '9.00',
        ]);
        $account = CustomerAccount::query()->where('customer_id', $customer->getKey())->firstOrFail();
        $this->assertSame('1000.00', $account->principal_balance);
        $this->assertSame('9.00', $account->interest_balance);
        $this->assertSame('1009.00', $account->total_payable);
    }

    public function test_payment_reversal_is_blocked_after_a_later_normal_payment(): void
    {
        [$shop, $owner, $customer] = $this->makeCustomerContext();
        $this->createPolicy($shop, $owner, '0.00000000', 'PRINCIPAL_FIRST', 30);
        $this->postSale($owner, $customer, '2026-11-01', '100.00', 'blocked-reversal-sale');
        $payment = app(PaymentPostingService::class)->post(
            $owner, $customer, '20.00', CarbonImmutable::parse('2026-11-10'),
            null, null, null, 'blocked-reversal-original',
        );
        app(PaymentPostingService::class)->post(
            $owner, $customer, '20.00', CarbonImmutable::parse('2026-11-11'),
            null, null, null, 'blocked-reversal-later',
        );

        $this->expectException(ConflictHttpException::class);
        app(PaymentReversalService::class)->correct(
            $owner, $customer, $payment, 'Historical correction', 'blocked-reversal-request',
        );
    }

    private function postSale(User $owner, Customer $customer, string $date, string $amount, string $key): Sale
    {
        return app(SalePostingService::class)->post(
            $owner, $customer, Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse($date), $amount, 'Payment posting test', $key,
        );
    }

    private function createPolicy(
        Shop $shop,
        User $owner,
        string $rate,
        string $waterfall,
        int $graceDays,
    ): void {
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
            'payment_waterfall' => $waterfall,
            'annual_settlement_month_1' => 3,
            'annual_settlement_day_1' => 30,
            'annual_settlement_month_2' => 10,
            'annual_settlement_day_2' => 30,
            'created_by' => $owner->getKey(),
            'created_at' => now(),
        ]);
    }

    /** @return array{Shop, User, Customer} */
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
