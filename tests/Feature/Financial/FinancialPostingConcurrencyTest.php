<?php

namespace Tests\Feature\Financial;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\InterestCalculationItem;
use App\Models\InterestSettlementRun;
use App\Models\Payment;
use App\Models\PaymentAllocationItem;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Modules\Financial\Application\AnnualInterestSettlementService;
use App\Modules\Financial\Application\PaymentPostingService;
use App\Modules\Financial\Application\ReconciliationService;
use App\Modules\Financial\Application\SalePostingService;
use App\Tenancy\CurrentShop;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;
use Throwable;

#[Group('mysql-locking')]
class FinancialPostingConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    private ?int $fixtureShopId = null;

    public function test_concurrent_normal_payments_cannot_overdraw_customer_payable(): void
    {
        [$shop, $owner, $customer] = $this->context('0.00000000', 30);
        app(SalePostingService::class)->post(
            $owner,
            $customer,
            Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-11-01'),
            '100.00',
            'Concurrent payment fixture',
            'concurrent-payment-sale',
        );

        $primary = DB::connection();
        $primary->beginTransaction();

        try {
            app(PaymentPostingService::class)->post(
                $owner,
                $customer,
                '70.00',
                CarbonImmutable::parse('2026-11-05'),
                'CASH',
                null,
                'First payment holds account lock',
                'concurrent-payment-first',
            );

            $worker = $this->runWorkerWhileLockHeld($shop, function () use ($owner, $customer): array {
                try {
                    app(PaymentPostingService::class)->post(
                        User::query()->findOrFail($owner->getKey()),
                        Customer::query()->findOrFail($customer->getKey()),
                        '50.00',
                        CarbonImmutable::parse('2026-11-06'),
                        'CASH',
                        null,
                        'Concurrent payment must be rejected',
                        'concurrent-payment-second',
                    );

                    return ['outcome' => 'posted'];
                } catch (ConflictHttpException $exception) {
                    return ['outcome' => 'rejected', 'exception' => $exception::class];
                }
            });

            $this->assertSame('rejected', $worker['outcome']);
            $this->assertSame(ConflictHttpException::class, $worker['exception']);
        } finally {
            if ($primary->transactionLevel() > 0) {
                $primary->rollBack();
            }
        }

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('70.00', Payment::query()->sum('amount'));
        $account = CustomerAccount::query()->where('customer_id', $customer->getKey())->firstOrFail();
        $this->assertSame('30.00', $account->principal_balance);
        $this->assertSame('30.00', $account->total_payable);
        $this->assertTrue(
            app(ReconciliationService::class)->reconcileCustomer($customer)['consistent'],
        );
    }

    public function test_payment_waits_for_concurrent_sale_and_allocates_against_committed_balance(): void
    {
        [$shop, $owner, $customer] = $this->context('0.00000000', 30);
        $firstSale = app(SalePostingService::class)->post(
            $owner,
            $customer,
            Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-11-01'),
            '100.00',
            'First FIFO sale',
            'sale-payment-race-first-sale',
        );

        $primary = DB::connection();
        $primary->beginTransaction();

        try {
            $secondSale = app(SalePostingService::class)->post(
                $owner,
                $customer,
                Sale::PAYMENT_CHOICE_UNPAID,
                CarbonImmutable::parse('2026-11-02'),
                '50.00',
                'Sale holds account lock',
                'sale-payment-race-second-sale',
            );

            $worker = $this->runWorkerWhileLockHeld($shop, function () use ($owner, $customer): array {
                $payment = app(PaymentPostingService::class)->post(
                    User::query()->findOrFail($owner->getKey()),
                    Customer::query()->findOrFail($customer->getKey()),
                    '120.00',
                    CarbonImmutable::parse('2026-11-03'),
                    'CASH',
                    null,
                    'Payment after concurrent sale',
                    'sale-payment-race-payment',
                );

                return ['outcome' => 'posted', 'payment_id' => $payment->getKey()];
            });

            $this->assertSame('posted', $worker['outcome']);
        } finally {
            if ($primary->transactionLevel() > 0) {
                $primary->rollBack();
            }
        }

        $items = PaymentAllocationItem::query()
            ->where('payment_id', $worker['payment_id'])
            ->orderBy('sale_fifo_sequence')
            ->get();

        $this->assertSame(
            [$firstSale->getKey(), $secondSale->getKey()],
            $items->pluck('sale_id')->unique()->map(fn ($id) => (int) $id)->values()->all(),
        );
        $allocated = $items->reduce(fn (BigDecimal $sum, PaymentAllocationItem $item): BigDecimal => $sum->plus((string) $item->amount), BigDecimal::zero());
        $this->assertSame('120.00', (string) $allocated->toScale(2));
        $this->assertSame('30.00', CustomerAccount::query()
            ->where('customer_id', $customer->getKey())
            ->firstOrFail()
            ->total_payable);
        $this->assertTrue(
            app(ReconciliationService::class)->reconcileCustomer($customer)['consistent'],
        );
    }

    public function test_payment_and_same_day_annual_settlement_serialize_without_duplicate_interest(): void
    {
        [$shop, $owner, $customer] = $this->context('3.00000000', 0);
        app(SalePostingService::class)->post(
            $owner,
            $customer,
            Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-10-01'),
            '1000.00',
            'Same-day settlement concurrency fixture',
            'payment-settlement-race-sale',
        );

        $primary = DB::connection();
        $primary->beginTransaction();

        try {
            app(PaymentPostingService::class)->post(
                $owner,
                $customer,
                '10.00',
                CarbonImmutable::parse('2026-10-30'),
                'CASH',
                null,
                'Payment wins same-day lock',
                'payment-settlement-race-payment',
            );

            $worker = $this->runWorkerWhileLockHeld($shop, function () use ($shop): array {
                $result = app(AnnualInterestSettlementService::class)
                    ->settleShopDate(Shop::query()->findOrFail($shop->getKey()), '2026-10-30');

                return ['outcome' => 'settled', 'result' => $result];
            });

            $this->assertSame('settled', $worker['outcome']);
            $this->assertSame(1, $worker['result']['completed']);
        } finally {
            if ($primary->transactionLevel() > 0) {
                $primary->rollBack();
            }
        }

        $this->assertSame(1, InterestCalculationItem::query()->where('posted_interest', '>', 0)->count());
        $this->assertSame('COMPLETED', InterestSettlementRun::query()->firstOrFail()->status);
        $account = CustomerAccount::query()->where('customer_id', $customer->getKey())->firstOrFail();
        $this->assertSame('28.00', $account->interest_balance);
        $this->assertSame('1018.00', $account->total_payable);
        $this->assertTrue(
            app(ReconciliationService::class)->reconcileCustomer($customer)['consistent'],
        );
    }

    private function context(string $rate, int $graceDays): array
    {
        $shop = Shop::factory()->create(['timezone' => 'Asia/Kolkata']);
        $this->fixtureShopId = (int) $shop->getKey();
        $owner = User::factory()->create([
            'shop_id' => $shop->getKey(),
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        app(CurrentShop::class)->set($shop);

        $customerId = DB::table('customers')->insertGetId([
            'shop_id' => $shop->getKey(),
            'name_original' => 'Financial Concurrency Customer',
            'name_original_language' => 'en',
            'name_en' => 'Financial Concurrency Customer',
            'status' => Customer::STATUS_ACTIVE,
            'created_by' => $owner->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $customer = Customer::query()->findOrFail($customerId);

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

        return [$shop, $owner, $customer];
    }

    private function runWorkerWhileLockHeld(Shop $shop, Closure $operation): array
    {
        $workerConnectionName = 'financial_posting_worker';
        $workerConnectionConfig = config('database.connections.'.DB::getDefaultConnection());
        $observerConnectionName = 'financial_posting_observer';
        $observerConnectionConfig = array_merge($workerConnectionConfig, [
            'username' => env('DB_MONITOR_USERNAME', 'root'),
            'password' => env('DB_MONITOR_PASSWORD', 'root'),
        ]);
        config(["database.connections.{$observerConnectionName}" => $observerConnectionConfig]);
        DB::purge($observerConnectionName);
        $observer = DB::connection($observerConnectionName);

        [$parentSocket, $childSocket] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $pid = pcntl_fork();

        if ($pid === -1) {
            fclose($parentSocket);
            fclose($childSocket);
            $this->fail('Unable to fork the financial posting worker.');
        }

        if ($pid === 0) {
            fclose($parentSocket);

            try {
                config([
                    'database.default' => $workerConnectionName,
                    "database.connections.{$workerConnectionName}" => $workerConnectionConfig,
                ]);
                DB::setDefaultConnection($workerConnectionName);
                DB::purge($workerConnectionName);
                DB::connection($workerConnectionName);
                $connectionId = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
                fwrite($childSocket, json_encode(['connection_id' => $connectionId], JSON_THROW_ON_ERROR)."\n");

                app(CurrentShop::class)->set(Shop::query()->findOrFail($shop->getKey()));
                $result = $operation();
                fwrite($childSocket, json_encode(['result' => $result], JSON_THROW_ON_ERROR)."\n");
                fclose($childSocket);
                exit(0);
            } catch (Throwable $exception) {
                fwrite($childSocket, json_encode([
                    'error' => $exception::class,
                    'message' => $exception->getMessage(),
                ], JSON_THROW_ON_ERROR)."\n");
                fclose($childSocket);
                exit(1);
            }
        }

        fclose($childSocket);
        $workerResult = null;
        $status = 0;

        try {
            $connection = json_decode((string) fgets($parentSocket), true, 512, JSON_THROW_ON_ERROR);
            $connectionId = (int) $connection['connection_id'];
            $deadline = microtime(true) + 10;
            $observedLockWait = false;

            do {
                $waiting = $observer->selectOne(
                    'SELECT COUNT(*) AS aggregate
                     FROM performance_schema.data_lock_waits AS waits
                     INNER JOIN performance_schema.threads AS requesting_thread
                       ON requesting_thread.THREAD_ID = waits.REQUESTING_THREAD_ID
                     WHERE requesting_thread.PROCESSLIST_ID = ?',
                    [$connectionId],
                );

                if ((int) $waiting->aggregate > 0) {
                    $observedLockWait = true;
                    break;
                }

                usleep(20_000);
            } while (microtime(true) < $deadline);

            if ($observedLockWait === false) {
                $process = $observer->selectOne(
                    'SELECT ID, USER, DB, COMMAND, TIME, STATE, INFO
                     FROM information_schema.processlist
                     WHERE ID = ?',
                    [$connectionId],
                );
                $primary = DB::connection();
                if ($primary->transactionLevel() > 0) {
                    $primary->rollBack();
                }
                $line = fgets($parentSocket);
                $this->fail('Worker did not wait for the customer lock: '.json_encode([
                    'process' => $process,
                    'worker' => $line === false ? null : trim($line),
                ], JSON_THROW_ON_ERROR));
            }

            DB::connection()->commit();
            $line = fgets($parentSocket);
            $message = $line === false ? null : json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            pcntl_waitpid($pid, $status);
            $workerResult = $message['result'] ?? null;

            $this->assertArrayNotHasKey('error', $message ?? []);
            $this->assertSame(0, pcntl_wexitstatus($status), json_encode($message));
            $this->assertIsArray($workerResult);

            return $workerResult;
        } finally {
            if (pcntl_waitpid($pid, $status, WNOHANG) === 0) {
                pcntl_waitpid($pid, $status);
            }
            fclose($parentSocket);
            DB::disconnect($observerConnectionName);
            DB::purge($observerConnectionName);
            DB::purge($workerConnectionName);
        }
    }

    protected function tearDown(): void
    {
        if ($this->fixtureShopId !== null) {
            $shopId = $this->fixtureShopId;
            $customerIds = DB::table('customers')->where('shop_id', $shopId)->pluck('id');
            $transactionIds = DB::table('financial_transactions')->where('shop_id', $shopId)->pluck('id');

            DB::table('audit_logs')
                ->where('entity_type', 'financial_transaction')
                ->whereIn('entity_id', $transactionIds)
                ->delete();
            DB::table('interest_settlement_items')->whereIn('customer_id', $customerIds)->delete();
            DB::table('interest_calculation_items')->whereIn('customer_id', $customerIds)->delete();
            DB::table('interest_calculation_runs')->whereIn('customer_id', $customerIds)->delete();
            DB::table('interest_settlement_runs')->whereIn('customer_id', $customerIds)->delete();
            DB::table('interest_lot_events')->whereIn('customer_id', $customerIds)->delete();
            DB::table('interest_lots')->whereIn('customer_id', $customerIds)->delete();
            DB::table('payment_allocation_items')->whereIn('customer_id', $customerIds)->delete();
            DB::table('payment_allocations')->whereIn('customer_id', $customerIds)->delete();
            DB::table('payment_revisions')->whereIn('customer_id', $customerIds)->delete();
            DB::table('sale_revisions')->whereIn('sale_id', DB::table('sales')->whereIn('customer_id', $customerIds)->pluck('id'))->delete();
            DB::table('ledger_entries')->whereIn('customer_id', $customerIds)->delete();
            DB::table('payments')->whereIn('customer_id', $customerIds)->delete();
            DB::table('account_balance_snapshots')->whereIn('customer_id', $customerIds)->delete();
            DB::table('sales')->whereIn('customer_id', $customerIds)->delete();
            DB::table('financial_transactions')->whereIn('id', $transactionIds)->delete();
            DB::table('idempotency_keys')->where('shop_id', $shopId)->delete();
            DB::table('customer_accounts')->whereIn('customer_id', $customerIds)->delete();
            DB::table('customer_interest_policy_versions')->whereIn('customer_id', $customerIds)->delete();
            DB::table('interest_policy_versions')->where('shop_id', $shopId)->delete();
            DB::table('interest_policies')->where('shop_id', $shopId)->delete();
            DB::table('customers')->whereIn('id', $customerIds)->delete();
            DB::table('users')->where('shop_id', $shopId)->delete();
            DB::table('shops')->where('id', $shopId)->delete();
            app(CurrentShop::class)->clear();
        }

        parent::tearDown();
    }
}
