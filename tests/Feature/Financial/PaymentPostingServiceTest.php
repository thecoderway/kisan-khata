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
use App\Modules\Financial\Application\SalePostingService;
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
        $this->assertSame('10.00', $account->interest_balance);
        $this->assertSame('1005.00', $account->total_payable);
    }

    public function test_interest_first_allocates_to_oldest_non_capitalized_interest_lot_before_principal(): void
    {
        [$shop, $owner, $customer] = $this->makeCustomerContext();
        $this->createPolicy($shop, $owner, '3.00000000', 'INTEREST_FIRST', 0);
        $sale = $this->postSale($owner, $customer, '2026-11-01', '1000.00', 'interest-first-sale');

        $payment = app(PaymentPostingService::class)->post(
            $owner, $customer, '10.00', CarbonImmutable::parse('2026-11-11'),
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
            'amount' => '10.00',
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
        $this->assertSame('5.00', $account->principal_balance);
        $this->assertSame('0.00', $account->interest_balance);
        $this->assertSame('5.00', $account->total_payable);
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
