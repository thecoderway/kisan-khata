<?php

namespace Tests\Feature\Database;

use App\Models\Customer;
use App\Models\FinancialTransaction;
use App\Models\Shop;
use App\Models\User;
use App\Tenancy\CurrentShop;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class GroupOneFinancialSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_one_tables_use_innodb_and_have_the_expected_keys(): void
    {
        foreach (['idempotency_keys', 'customer_accounts', 'financial_transactions'] as $table) {
            $engine = DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->value('ENGINE');

            $this->assertSame('InnoDB', $engine);
        }

        $this->assertContains('idem_shop_operation_key_unique', collect(Schema::getIndexes('idempotency_keys'))->pluck('name'));
        $this->assertContains('acct_shop_customer_unique', collect(Schema::getIndexes('customer_accounts'))->pluck('name'));
        $this->assertContains('ft_shop_number_unique', collect(Schema::getIndexes('financial_transactions'))->pluck('name'));
    }

    public function test_idempotency_identity_is_unique_per_shop_and_operation(): void
    {
        $shop = Shop::factory()->create();
        $this->insertIdempotencyKey($shop->id);

        $this->expectException(QueryException::class);
        $this->insertIdempotencyKey($shop->id);
    }

    public function test_idempotency_status_check_rejects_unknown_status(): void
    {
        $shop = Shop::factory()->create();

        $this->expectException(QueryException::class);
        $this->insertIdempotencyKey($shop->id, status: 'UNKNOWN');
    }

    public function test_customer_account_is_unique_per_shop_and_customer(): void
    {
        [$shop, $customer] = $this->shopAndCustomer();

        $this->insertCustomerAccount($shop->id, $customer->id);

        $this->expectException(QueryException::class);
        $this->insertCustomerAccount($shop->id, $customer->id);
    }

    public function test_customer_account_rejects_negative_projection_values(): void
    {
        [$shop, $customer] = $this->shopAndCustomer();

        $this->expectException(QueryException::class);
        DB::table('customer_accounts')->insert($this->accountAttributes($shop->id, $customer->id, [
            'principal_balance' => '-1.00',
            'total_payable' => '-1.00',
        ]));
    }

    public function test_customer_account_rejects_inconsistent_total_projection(): void
    {
        [$shop, $customer] = $this->shopAndCustomer();

        $this->expectException(QueryException::class);
        DB::table('customer_accounts')->insert($this->accountAttributes($shop->id, $customer->id, [
            'principal_balance' => '10.00',
            'interest_balance' => '2.00',
            'total_payable' => '11.00',
        ]));
    }

    public function test_customer_account_rejects_cross_shop_customer_reference(): void
    {
        [$shop] = $this->shopAndCustomer();
        [, $otherCustomer] = $this->shopAndCustomer();

        $this->expectException(QueryException::class);
        $this->insertCustomerAccount($shop->id, $otherCustomer->id);
    }

    public function test_financial_transaction_rejects_cross_shop_customer_reference(): void
    {
        [$shop, $actor] = $this->shopAndCustomerWithActor();
        [, $otherCustomer] = $this->shopAndCustomer();

        $this->expectException(QueryException::class);
        $this->insertFinancialTransaction($shop->id, $otherCustomer->id, $actor->id, $actor->id);
    }

    public function test_financial_transaction_rejects_cross_shop_actor_reference(): void
    {
        [$shop, $customer, $actor] = $this->shopCustomerAndActor();
        $otherShop = Shop::factory()->create();
        $otherActor = User::factory()->create([
            'shop_id' => $otherShop->id,
            'role' => User::ROLE_OWNER,
        ]);

        $this->expectException(QueryException::class);
        $this->insertFinancialTransaction($shop->id, $customer->id, $otherActor->id, $actor->id);
    }

    public function test_financial_transaction_rejects_platform_actor_without_shop(): void
    {
        [$shop, $customer] = $this->shopAndCustomer();
        $platformUser = User::factory()->create([
            'shop_id' => null,
            'role' => User::ROLE_PLATFORM_OWNER,
        ]);

        $this->expectException(QueryException::class);
        $this->insertFinancialTransaction($shop->id, $customer->id, $platformUser->id, $platformUser->id);
    }

    public function test_financial_transaction_number_is_unique_per_shop(): void
    {
        [$shop, $customer, $actor] = $this->shopCustomerAndActor();
        $this->insertFinancialTransaction(
            $shop->id,
            $customer->id,
            $actor->id,
            $actor->id,
            transactionNumber: 'TXN-DUPLICATE'
        );

        $this->expectException(QueryException::class);
        $this->insertFinancialTransaction(
            $shop->id,
            $customer->id,
            $actor->id,
            $actor->id,
            transactionNumber: 'TXN-DUPLICATE'
        );
    }

    public function test_financial_transaction_rejects_unknown_type(): void
    {
        [$shop, $customer, $actor] = $this->shopCustomerAndActor();

        $this->expectException(QueryException::class);
        $this->insertFinancialTransaction($shop->id, $customer->id, $actor->id, $actor->id, type: 'UNKNOWN');
    }

    public function test_financial_transaction_model_prevents_mutation_and_deletion(): void
    {
        [$shop, $customer, $actor] = $this->shopCustomerAndActor();
        $id = $this->insertFinancialTransaction($shop->id, $customer->id, $actor->id, $actor->id);
        app(CurrentShop::class)->set($shop);

        $transaction = FinancialTransaction::withoutGlobalScopes()->findOrFail($id);

        try {
            $transaction->update(['source_type' => 'changed']);
            $this->fail('Expected update to be blocked.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        $transaction = FinancialTransaction::withoutGlobalScopes()->findOrFail($id);

        try {
            $transaction->delete();
            $this->fail('Expected deletion to be blocked.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('cannot be deleted', $exception->getMessage());
        }
    }

    private function shopAndCustomer(): array
    {
        $shop = Shop::factory()->create();
        $actor = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
        ]);

        $customerId = (int) DB::table('customers')->insertGetId([
            'shop_id' => $shop->id,
            'created_by' => $actor->id,
            'name_original' => 'Financial Test Customer',
            'name_original_language' => 'en',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $customer = Customer::withoutGlobalScopes()->findOrFail($customerId);

        return [$shop, $customer];
    }

    private function shopAndCustomerWithActor(): array
    {
        [$shop, $customer] = $this->shopAndCustomer();

        return [$shop, $customer->creator];
    }

    private function shopCustomerAndActor(): array
    {
        [$shop, $customer] = $this->shopAndCustomer();

        return [$shop, $customer, $customer->creator];
    }

    private function insertIdempotencyKey(int $shopId, string $status = 'IN_PROGRESS'): int
    {
        return (int) DB::table('idempotency_keys')->insertGetId([
            'shop_id' => $shopId,
            'operation_type' => 'SALE_POSTING',
            'key_hash' => str_repeat('a', 64),
            'request_hash' => str_repeat('b', 64),
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function accountAttributes(int $shopId, int $customerId, array $overrides = []): array
    {
        return array_merge([
            'shop_id' => $shopId,
            'customer_id' => $customerId,
            'principal_balance' => '0.00',
            'interest_balance' => '0.00',
            'total_payable' => '0.00',
            'projection_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    private function insertCustomerAccount(int $shopId, int $customerId): int
    {
        return (int) DB::table('customer_accounts')->insertGetId(
            $this->accountAttributes($shopId, $customerId)
        );
    }

    private function insertFinancialTransaction(
        int $shopId,
        int $customerId,
        int $createdBy,
        int $postedBy,
        string $type = 'SALE_POSTING',
        ?string $transactionNumber = null
    ): int {
        static $number = 0;
        $number++;

        return (int) DB::table('financial_transactions')->insertGetId([
            'shop_id' => $shopId,
            'customer_id' => $customerId,
            'transaction_number' => $transactionNumber ?? 'TXN-'.str_pad((string) $number, 8, '0', STR_PAD_LEFT),
            'transaction_type' => $type,
            'effective_date' => now()->toDateString(),
            'occurred_at' => now(),
            'created_by' => $createdBy,
            'posted_by' => $postedBy,
            'posted_at' => now(),
            'created_at' => now(),
        ]);
    }
}
