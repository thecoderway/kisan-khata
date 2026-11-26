<?php

namespace Tests\Feature\Database;

use App\Models\Customer;
use App\Models\PaymentAllocation;
use App\Models\PaymentAllocationItem;
use App\Models\Shop;
use App\Models\User;
use App\Tenancy\CurrentShop;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class GroupThreePaymentAllocationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_allocation_tables_have_expected_keys_and_innodb_engine(): void
    {
        foreach (['payment_allocations', 'payment_allocation_items'] as $table) {
            $engine = DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->value('ENGINE');

            $this->assertSame('InnoDB', $engine);
        }

        $this->assertContains(
            'pa_shop_customer_payment_unique',
            collect(Schema::getIndexes('payment_allocations'))->pluck('name')
        );
        $this->assertContains(
            'pai_allocation_order_unique',
            collect(Schema::getIndexes('payment_allocation_items'))->pluck('name')
        );
    }

    public function test_allocation_header_rejects_payment_from_another_customer(): void
    {
        [$shop, $customer, $actor, $transaction, $payment] = $this->fixture();
        $otherCustomer = $this->customer($shop, $actor);
        $otherTransaction = $this->transaction($shop, $otherCustomer, $actor);
        $otherPayment = $this->payment($shop, $otherCustomer, $actor, $otherTransaction);

        $this->expectException(QueryException::class);
        DB::table('payment_allocations')->insert([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'payment_id' => $otherPayment,
            'allocation_type' => 'NORMAL_FIFO',
            'waterfall_policy_version_id' => $this->policyVersion($shop, $actor),
            'allocation_effective_at' => now(),
            'total_allocated' => '100.00',
            'created_at' => now(),
        ]);
    }

    public function test_allocation_item_must_match_its_header_payment_and_sale_customer(): void
    {
        [$shop, $customer, $actor, $transaction, $payment, $sale] = $this->fixture(withSale: true);
        $allocation = $this->allocation($shop, $customer, $payment, $actor);

        $otherCustomer = $this->customer($shop, $actor);
        $otherTransaction = $this->transaction($shop, $otherCustomer, $actor);
        $otherSale = $this->sale($shop, $otherCustomer, $actor, $otherTransaction);

        $this->expectException(QueryException::class);
        $this->allocationItem($shop, $customer, $allocation, $payment, $otherSale);
    }

    public function test_allocation_item_rejects_interest_component_without_source_lot(): void
    {
        [$shop, $customer, $actor, $transaction, $payment, $sale] = $this->fixture(withSale: true);
        $allocation = $this->allocation($shop, $customer, $payment, $actor);

        $this->expectException(QueryException::class);
        $this->allocationItem(
            $shop,
            $customer,
            $allocation,
            $payment,
            $sale,
            component: 'NON_CAPITALIZED_INTEREST'
        );
    }

    public function test_allocation_models_are_append_only(): void
    {
        [$shop, $customer, $actor, $transaction, $payment, $sale] = $this->fixture(withSale: true);
        $allocationId = $this->allocation($shop, $customer, $payment, $actor);
        $itemId = $this->allocationItem($shop, $customer, $allocationId, $payment, $sale);

        app(CurrentShop::class)->set($shop);

        $allocation = PaymentAllocation::withoutGlobalScopes()->findOrFail($allocationId);
        try {
            $allocation->setAttribute('total_allocated', '99.00');
            $allocation->save();
            $this->fail('Expected allocation header mutation to be blocked.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        $item = PaymentAllocationItem::withoutGlobalScopes()->findOrFail($itemId);
        try {
            $item->delete();
            $this->fail('Expected allocation item deletion to be blocked.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('cannot be deleted', $exception->getMessage());
        }
    }

    private function fixture(bool $withSale = false): array
    {
        $shop = Shop::factory()->create();
        $actor = User::factory()->create(['shop_id' => $shop->id, 'role' => User::ROLE_OWNER]);
        $customer = $this->customer($shop, $actor);
        $transaction = $this->transaction($shop, $customer, $actor);
        $payment = $this->payment($shop, $customer, $actor, $transaction);
        $saleTransaction = $withSale ? $this->transaction($shop, $customer, $actor, 'SALE_POSTING') : null;
        $sale = $withSale ? $this->sale($shop, $customer, $actor, $saleTransaction) : null;

        return [$shop, $customer, $actor, $transaction, $payment, $sale];
    }

    private function customer(Shop $shop, User $actor): Customer
    {
        $id = (int) DB::table('customers')->insertGetId([
            'shop_id' => $shop->id,
            'created_by' => $actor->id,
            'name_original' => 'Allocation Customer',
            'name_original_language' => 'en',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Customer::withoutGlobalScopes()->findOrFail($id);
    }

    private function transaction(Shop $shop, Customer $customer, User $actor, string $type = 'PAYMENT_POSTING'): int
    {
        static $sequence = 0;
        $sequence++;

        return (int) DB::table('financial_transactions')->insertGetId([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'transaction_number' => 'G3-TXN-'.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT),
            'transaction_type' => $type,
            'effective_date' => now()->toDateString(),
            'occurred_at' => now(),
            'created_by' => $actor->id,
            'posted_by' => $actor->id,
            'posted_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function payment(Shop $shop, Customer $customer, User $actor, int $transactionId): int
    {
        static $sequence = 0;
        $sequence++;

        return (int) DB::table('payments')->insertGetId([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'payment_number' => 'G3-PAY-'.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT),
            'payment_type' => 'NORMAL',
            'payment_date' => now()->toDateString(),
            'amount' => '100.00',
            'financial_transaction_id' => $transactionId,
            'created_by' => $actor->id,
            'posted_by' => $actor->id,
            'posted_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function sale(Shop $shop, Customer $customer, User $actor, int $transactionId): int
    {
        static $sequence = 0;
        $sequence++;

        return (int) DB::table('sales')->insertGetId([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'sale_number' => 'G3-SALE-'.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT),
            'sale_date' => now()->toDateString(),
            'amount' => '100.00',
            'payment_status' => 'UNPAID',
            'interest_settlement_status' => 'PENDING',
            'sale_payment_choice' => 'UNPAID',
            'financial_transaction_id' => $transactionId,
            'created_by' => $actor->id,
            'posted_by' => $actor->id,
            'posted_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function policyVersion(Shop $shop, User $actor): int
    {
        $policyId = (int) DB::table('interest_policies')->insertGetId([
            'shop_id' => $shop->id,
            'policy_code' => 'DEFAULT_INTEREST',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('interest_policy_versions')->insertGetId([
            'shop_id' => $shop->id,
            'interest_policy_id' => $policyId,
            'version_number' => 1,
            'effective_from' => now()->toDateString(),
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
    }

    private function allocation(Shop $shop, Customer $customer, int $paymentId, User $actor): int
    {
        $policyVersionId = $this->policyVersion($shop, $actor);

        return (int) DB::table('payment_allocations')->insertGetId([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'payment_id' => $paymentId,
            'allocation_type' => 'NORMAL_FIFO',
            'waterfall_policy_version_id' => $policyVersionId,
            'allocation_effective_at' => now(),
            'total_allocated' => '100.00',
            'created_at' => now(),
        ]);
    }

    private function allocationItem(
        Shop $shop,
        Customer $customer,
        int $allocationId,
        int $paymentId,
        int $saleId,
        string $component = 'PRINCIPAL'
    ): int {
        return (int) DB::table('payment_allocation_items')->insertGetId([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'allocation_id' => $allocationId,
            'payment_id' => $paymentId,
            'sale_id' => $saleId,
            'component_type' => $component,
            'source_interest_lot_id' => null,
            'amount' => '100.00',
            'sale_fifo_sequence' => 1,
            'component_sequence' => 1,
            'lot_sequence' => 0,
            'created_at' => now(),
        ]);
    }
}
