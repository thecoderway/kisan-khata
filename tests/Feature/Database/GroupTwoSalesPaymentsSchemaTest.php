<?php

namespace Tests\Feature\Database;

use App\Models\Customer;
use App\Models\FinancialTransaction;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleRevision;
use App\Models\PaymentRevision;
use App\Models\Shop;
use App\Models\User;
use App\Tenancy\CurrentShop;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class GroupTwoSalesPaymentsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_two_tables_use_innodb_and_expected_unique_keys(): void
    {
        foreach (['sales', 'sale_revisions', 'payments', 'payment_revisions'] as $table) {
            $engine = DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->value('ENGINE');

            $this->assertSame('InnoDB', $engine);
        }

        $this->assertContains('sales_shop_number_unique', collect(Schema::getIndexes('sales'))->pluck('name'));
        $this->assertContains('payments_shop_number_unique', collect(Schema::getIndexes('payments'))->pluck('name'));
        $this->assertContains('payments_shop_source_sale_unique', collect(Schema::getIndexes('payments'))->pluck('name'));
        $this->assertContains('sale_rev_shop_sale_number_unique', collect(Schema::getIndexes('sale_revisions'))->pluck('name'));
        $this->assertContains('payment_rev_shop_payment_number_unique', collect(Schema::getIndexes('payment_revisions'))->pluck('name'));
    }

    public function test_sales_reject_non_positive_amounts_and_unknown_statuses(): void
    {
        [$shop, $customer, $actor, $transaction] = $this->shopCustomerActorAndTransaction();

        try {
            $this->insertSale($shop, $customer, $actor, $transaction, amount: '0.00');
            $this->fail('Expected positive amount CHECK to reject zero.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        try {
            $this->insertSale($shop, $customer, $actor, $transaction, paymentStatus: 'UNKNOWN');
            $this->fail('Expected payment status CHECK to reject an unknown value.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
    }

    public function test_sale_rejects_cross_shop_customer_and_transaction_references(): void
    {
        [$shop, $customer, $actor, $transaction] = $this->shopCustomerActorAndTransaction();
        [$otherShop, $otherCustomer, $otherActor, $otherTransaction] = $this->shopCustomerActorAndTransaction();

        $this->expectException(QueryException::class);
        $this->insertSale($shop, $otherCustomer, $actor, $transaction);
    }

    public function test_sale_rejects_transaction_from_same_shop_but_another_customer(): void
    {
        [$shop, $customer, $actor] = $this->shopCustomerActorAndTransaction();
        $otherCustomer = $this->createCustomer($shop, $actor);
        $otherTransaction = $this->createFinancialTransaction($shop, $otherCustomer, $actor);

        $this->expectException(QueryException::class);
        $this->insertSale($shop, $customer, $actor, $otherTransaction);
    }

    public function test_payment_rejects_source_sale_from_another_customer(): void
    {
        [$shop, $customer, $actor, $transaction] = $this->shopCustomerActorAndTransaction();
        $saleId = $this->insertSale($shop, $customer, $actor, $transaction);
        $otherCustomer = $this->createCustomer($shop, $actor);
        $otherTransaction = $this->createFinancialTransaction($shop, $otherCustomer, $actor);

        $this->expectException(QueryException::class);
        $this->insertPayment($shop, $otherCustomer, $actor, $otherTransaction, sourceSaleId: $saleId, type: 'SALE_DAY_AUTO');
    }

    public function test_only_one_sale_day_automatic_payment_can_reference_a_sale(): void
    {
        [$shop, $customer, $actor, $transaction] = $this->shopCustomerActorAndTransaction();
        $saleId = $this->insertSale($shop, $customer, $actor, $transaction);

        $this->insertPayment($shop, $customer, $actor, $transaction, sourceSaleId: $saleId, type: 'SALE_DAY_AUTO');

        $this->expectException(QueryException::class);
        $this->insertPayment($shop, $customer, $actor, $transaction, sourceSaleId: $saleId, type: 'SALE_DAY_AUTO');
    }

    public function test_payment_type_and_source_consistency_are_enforced(): void
    {
        [$shop, $customer, $actor, $transaction] = $this->shopCustomerActorAndTransaction();

        $this->expectException(QueryException::class);
        $this->insertPayment($shop, $customer, $actor, $transaction, type: 'SALE_DAY_AUTO');
    }

    public function test_payment_reversal_must_reference_payment_for_same_shop_and_customer(): void
    {
        [$shop, $customer, $actor, $transaction] = $this->shopCustomerActorAndTransaction();
        $otherCustomer = $this->createCustomer($shop, $actor);
        $otherTransaction = $this->createFinancialTransaction($shop, $otherCustomer, $actor);
        $originalPaymentId = $this->insertPayment($shop, $customer, $actor, $transaction);

        $this->expectException(QueryException::class);
        $this->insertPayment(
            $shop,
            $otherCustomer,
            $actor,
            $otherTransaction,
            type: 'REVERSAL',
            reversalOfPaymentId: $originalPaymentId
        );
    }

    public function test_posted_sale_and_payment_models_are_immutable(): void
    {
        [$shop, $customer, $actor, $transaction] = $this->shopCustomerActorAndTransaction();
        $saleId = $this->insertSale($shop, $customer, $actor, $transaction);
        $paymentId = $this->insertPayment($shop, $customer, $actor, $transaction);
        app(CurrentShop::class)->set($shop);

        $sale = Sale::withoutGlobalScopes()->findOrFail($saleId);
        try {
            $sale->setAttribute('amount', '999.00');
            $sale->save();
            $this->fail('Expected posted Sale update to be blocked.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        $payment = Payment::withoutGlobalScopes()->findOrFail($paymentId);
        try {
            $payment->setAttribute('amount', '999.00');
            $payment->save();
            $this->fail('Expected posted Payment update to be blocked.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }
    }

    public function test_sale_revision_requires_reason_and_is_unique_per_revision_number(): void
    {
        [$shop, $customer, $actor, $transaction] = $this->shopCustomerActorAndTransaction();
        $saleId = $this->insertSale($shop, $customer, $actor, $transaction);

        DB::table('sale_revisions')->insert([
            'shop_id' => $shop->id,
            'sale_id' => $saleId,
            'revision_number' => 1,
            'old_values' => json_encode(['description' => 'old']),
            'new_values' => json_encode(['description' => 'new']),
            'correction_reason' => 'Corrected description',
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'created_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('sale_revisions')->insert([
            'shop_id' => $shop->id,
            'sale_id' => $saleId,
            'revision_number' => 1,
            'old_values' => json_encode(['description' => 'old']),
            'new_values' => json_encode(['description' => 'other']),
            'correction_reason' => 'Duplicate revision',
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function test_payment_revision_requires_customer_consistent_payment_references(): void
    {
        [$shop, $customer, $actor, $transaction] = $this->shopCustomerActorAndTransaction();
        $paymentId = $this->insertPayment($shop, $customer, $actor, $transaction);
        $otherCustomer = $this->createCustomer($shop, $actor);
        $otherTransaction = $this->createFinancialTransaction($shop, $otherCustomer, $actor);
        $otherPaymentId = $this->insertPayment($shop, $otherCustomer, $actor, $otherTransaction);

        $this->expectException(QueryException::class);
        DB::table('payment_revisions')->insert([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'payment_id' => $paymentId,
            'revision_number' => 1,
            'revision_type' => 'REVERSAL',
            'old_values' => json_encode(['amount' => '100.00']),
            'correction_reason' => 'Correction',
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'reversal_payment_id' => $otherPaymentId,
            'created_at' => now(),
        ]);
    }

    public function test_sale_revision_and_payment_revision_models_are_append_only(): void
    {
        [$shop, $customer, $actor, $transaction] = $this->shopCustomerActorAndTransaction();
        $saleId = $this->insertSale($shop, $customer, $actor, $transaction);
        $paymentId = $this->insertPayment($shop, $customer, $actor, $transaction);

        $saleRevisionId = (int) DB::table('sale_revisions')->insertGetId([
            'shop_id' => $shop->id,
            'sale_id' => $saleId,
            'revision_number' => 1,
            'old_values' => json_encode(['description' => 'old']),
            'new_values' => json_encode(['description' => 'new']),
            'correction_reason' => 'Corrected description',
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'created_at' => now(),
        ]);
        $paymentRevisionId = (int) DB::table('payment_revisions')->insertGetId([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'payment_id' => $paymentId,
            'revision_number' => 1,
            'revision_type' => 'REVERSAL',
            'old_values' => json_encode(['amount' => '100.00']),
            'correction_reason' => 'Reversed duplicate receipt',
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'reversal_payment_id' => $this->insertPayment(
                $shop,
                $customer,
                $actor,
                $transaction,
                type: 'REVERSAL',
                reversalOfPaymentId: $paymentId
            ),
            'created_at' => now(),
        ]);

        app(CurrentShop::class)->set($shop);

        $saleRevision = SaleRevision::withoutGlobalScopes()->findOrFail($saleRevisionId);
        try {
            $saleRevision->setAttribute('correction_reason', 'mutated');
            $saleRevision->save();
            $this->fail('Expected Sale revision update to be blocked.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        $paymentRevision = PaymentRevision::withoutGlobalScopes()->findOrFail($paymentRevisionId);
        try {
            $paymentRevision->delete();
            $this->fail('Expected Payment revision deletion to be blocked.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('cannot be deleted', $exception->getMessage());
        }
    }

    private function shopCustomerActorAndTransaction(): array
    {
        $shop = Shop::factory()->create();
        $actor = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
        ]);
        $customer = $this->createCustomer($shop, $actor);
        $transaction = $this->createFinancialTransaction($shop, $customer, $actor);

        return [$shop, $customer, $actor, $transaction];
    }

    private function createCustomer(Shop $shop, User $actor): Customer
    {
        $id = (int) DB::table('customers')->insertGetId([
            'shop_id' => $shop->id,
            'created_by' => $actor->id,
            'name_original' => 'Group Two Customer',
            'name_original_language' => 'en',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Customer::withoutGlobalScopes()->findOrFail($id);
    }

    private function createFinancialTransaction(Shop $shop, Customer $customer, User $actor): int
    {
        static $sequence = 0;
        $sequence++;

        return (int) DB::table('financial_transactions')->insertGetId([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'transaction_number' => 'G2-TXN-'.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT),
            'transaction_type' => 'SALE_POSTING',
            'effective_date' => now()->toDateString(),
            'occurred_at' => now(),
            'created_by' => $actor->id,
            'posted_by' => $actor->id,
            'posted_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function insertSale(
        Shop $shop,
        Customer $customer,
        User $actor,
        int $transactionId,
        string $amount = '100.00',
        string $paymentStatus = 'UNPAID'
    ): int {
        static $sequence = 0;
        $sequence++;

        return (int) DB::table('sales')->insertGetId([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'sale_number' => 'G2-SALE-'.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT),
            'sale_date' => now()->toDateString(),
            'amount' => $amount,
            'payment_status' => $paymentStatus,
            'interest_settlement_status' => 'PENDING',
            'sale_payment_choice' => 'UNPAID',
            'financial_transaction_id' => $transactionId,
            'created_by' => $actor->id,
            'posted_by' => $actor->id,
            'posted_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function insertPayment(
        Shop $shop,
        Customer $customer,
        User $actor,
        int $transactionId,
        ?int $sourceSaleId = null,
        string $type = 'NORMAL',
        ?int $reversalOfPaymentId = null
    ): int {
        static $sequence = 0;
        $sequence++;

        return (int) DB::table('payments')->insertGetId([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'payment_number' => 'G2-PAY-'.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT),
            'payment_type' => $type,
            'source_sale_id' => $sourceSaleId,
            'payment_date' => now()->toDateString(),
            'amount' => '100.00',
            'financial_transaction_id' => $transactionId,
            'reversal_of_payment_id' => $reversalOfPaymentId,
            'created_by' => $actor->id,
            'posted_by' => $actor->id,
            'posted_at' => now(),
            'created_at' => now(),
        ]);
    }
}
