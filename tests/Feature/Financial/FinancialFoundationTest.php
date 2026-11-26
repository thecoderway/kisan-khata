<?php

namespace Tests\Feature\Financial;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\FinancialTransaction;
use App\Models\IdempotencyKey;
use App\Models\Shop;
use App\Models\User;
use App\Modules\Financial\Application\CustomerAccountLockService;
use App\Modules\Financial\Application\FinancialAuditService;
use App\Modules\Financial\Application\FinancialTransactionService;
use App\Tenancy\CurrentShop;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class FinancialFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_account_lock_creates_and_reuses_one_shop_scoped_account(): void
    {
        [, , $customer] = $this->makeCustomerContext();

        $service = app(CustomerAccountLockService::class);

        $first = DB::transaction(fn (): CustomerAccount => $service->lockForCustomer($customer));
        $second = DB::transaction(fn (): CustomerAccount => $service->lockForCustomer($customer));

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame('0.00', $second->principal_balance);
        $this->assertSame('0.00', $second->interest_balance);
        $this->assertSame('0.00', $second->total_payable);
        $this->assertDatabaseCount('customer_accounts', 1);
    }

    public function test_financial_transaction_header_and_audit_are_posted_atomically(): void
    {
        [$shop, $owner, $customer] = $this->makeCustomerContext();
        $transactions = app(FinancialTransactionService::class);
        $audit = app(FinancialAuditService::class);

        $transaction = $transactions->executeForCustomer(
            $owner,
            $customer,
            'SALE_POSTING',
            'sale-post-001',
            ['amount' => '500.00', 'sale_date' => '2026-11-26'],
            function (CustomerAccount $account, IdempotencyKey $key) use ($transactions, $audit, $owner, $customer): FinancialTransaction {
                $this->assertSame($customer->getKey(), $account->customer_id);

                $transaction = $transactions->createHeader(
                    $owner,
                    $customer,
                    $key,
                    FinancialTransaction::TYPE_SALE_POSTING,
                    CarbonImmutable::parse('2026-11-26'),
                );

                $audit->recordTransaction(
                    $owner,
                    $customer,
                    $transaction,
                    'financial.sale_posted',
                    ['amount' => '500.00'],
                    null,
                    'Initial sale posting',
                );

                return $transaction;
            },
        );

        $this->assertSame($shop->getKey(), (int) $transaction->shop_id);
        $this->assertSame($customer->getKey(), (int) $transaction->customer_id);
        $this->assertSame($owner->getKey(), (int) $transaction->created_by);
        $this->assertSame($owner->getKey(), (int) $transaction->posted_by);
        $this->assertSame(FinancialTransaction::TYPE_SALE_POSTING, $transaction->transaction_type);
        $this->assertSame('2026-11-26', $transaction->effective_date->toDateString());
        $this->assertStringStartsWith('FT-', $transaction->transaction_number);

        $this->assertDatabaseHas('idempotency_keys', [
            'shop_id' => $shop->getKey(),
            'operation_type' => 'SALE_POSTING',
            'status' => IdempotencyKey::STATUS_COMPLETED,
            'resource_type' => 'financial_transaction',
            'resource_id' => $transaction->getKey(),
            'response_code' => 201,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'shop_id' => $shop->getKey(),
            'user_id' => $owner->getKey(),
            'action' => 'financial.sale_posted',
            'entity_type' => 'financial_transaction',
            'entity_id' => $transaction->getKey(),
            'reason' => 'Initial sale posting',
        ]);
    }

    public function test_idempotent_replay_returns_original_transaction_without_reexecuting_operation(): void
    {
        [, $owner, $customer] = $this->makeCustomerContext();
        $transactions = app(FinancialTransactionService::class);
        $executions = 0;

        $operation = function (CustomerAccount $account, IdempotencyKey $key) use ($transactions, $owner, $customer, &$executions): FinancialTransaction {
            $executions++;

            return $transactions->createHeader(
                $owner,
                $customer,
                $key,
                FinancialTransaction::TYPE_PAYMENT_POSTING,
                CarbonImmutable::parse('2026-11-26'),
            );
        };

        $first = $transactions->executeForCustomer(
            $owner,
            $customer,
            'PAYMENT_POSTING',
            'payment-request-001',
            ['amount' => '125.00', 'payment_date' => '2026-11-26'],
            $operation,
        );

        $replayed = $transactions->executeForCustomer(
            $owner,
            $customer,
            'PAYMENT_POSTING',
            'payment-request-001',
            ['payment_date' => '2026-11-26', 'amount' => '125.00'],
            $operation,
        );

        $this->assertSame($first->getKey(), $replayed->getKey());
        $this->assertSame(1, $executions);
        $this->assertDatabaseCount('idempotency_keys', 1);
        $this->assertDatabaseCount('financial_transactions', 1);
    }

    public function test_reusing_an_idempotency_key_for_a_different_request_returns_conflict(): void
    {
        [, $owner, $customer] = $this->makeCustomerContext();
        $transactions = app(FinancialTransactionService::class);
        $operation = fn (CustomerAccount $account, IdempotencyKey $key): FinancialTransaction => $transactions->createHeader(
            $owner,
            $customer,
            $key,
            FinancialTransaction::TYPE_PAYMENT_POSTING,
            CarbonImmutable::parse('2026-11-26'),
        );

        $transactions->executeForCustomer(
            $owner,
            $customer,
            'PAYMENT_POSTING',
            'payment-request-002',
            ['amount' => '125.00'],
            $operation,
        );

        try {
            $transactions->executeForCustomer(
                $owner,
                $customer,
                'PAYMENT_POSTING',
                'payment-request-002',
                ['amount' => '150.00'],
                $operation,
            );

            $this->fail('A reused key with a different request fingerprint must be rejected.');
        } catch (ConflictHttpException) {
            $this->assertDatabaseCount('idempotency_keys', 1);
            $this->assertDatabaseCount('financial_transactions', 1);
        }
    }

    public function test_failed_financial_operation_rolls_back_account_key_transaction_and_audit(): void
    {
        [$shop, $owner, $customer] = $this->makeCustomerContext();
        $transactions = app(FinancialTransactionService::class);
        $audit = app(FinancialAuditService::class);

        try {
            $transactions->executeForCustomer(
                $owner,
                $customer,
                'SALE_POSTING',
                'sale-post-rollback',
                ['amount' => '500.00'],
                function (CustomerAccount $account, IdempotencyKey $key) use ($transactions, $audit, $owner, $customer): FinancialTransaction {
                    $transaction = $transactions->createHeader(
                        $owner,
                        $customer,
                        $key,
                        FinancialTransaction::TYPE_SALE_POSTING,
                        CarbonImmutable::parse('2026-11-26'),
                    );

                    $audit->recordTransaction(
                        $owner,
                        $customer,
                        $transaction,
                        'financial.sale_posted',
                        ['amount' => '500.00'],
                    );

                    throw new RuntimeException('Simulated downstream posting failure.');
                },
            );

            $this->fail('The simulated posting failure should escape the transaction.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated downstream posting failure.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('customer_accounts', [
            'shop_id' => $shop->getKey(),
            'customer_id' => $customer->getKey(),
        ]);
        $this->assertDatabaseMissing('idempotency_keys', [
            'shop_id' => $shop->getKey(),
            'operation_type' => 'SALE_POSTING',
        ]);
        $this->assertDatabaseMissing('financial_transactions', [
            'shop_id' => $shop->getKey(),
            'customer_id' => $customer->getKey(),
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'shop_id' => $shop->getKey(),
            'entity_type' => 'financial_transaction',
        ]);
    }

    public function test_financial_operation_rejects_a_customer_from_another_shop(): void
    {
        [$shopA, $ownerA] = $this->makeCustomerContext();
        [$shopB, , $customerB] = $this->makeCustomerContext();

        app(CurrentShop::class)->set($shopA);

        try {
            app(FinancialTransactionService::class)->executeForCustomer(
                $ownerA,
                $customerB,
                'SALE_POSTING',
                'cross-shop-request',
                ['amount' => '500.00'],
                fn (): never => throw new LogicException('The operation callback must not execute.'),
            );

            $this->fail('Cross-shop financial operations must be rejected.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('customer_accounts', [
                'shop_id' => $shopB->getKey(),
                'customer_id' => $customerB->getKey(),
            ]);
        }
    }

    /**
     * @return array{Shop, User, Customer}
     */
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
            'created_by' => $owner->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$shop, $owner, Customer::query()->findOrFail($customerId)];
    }
}
