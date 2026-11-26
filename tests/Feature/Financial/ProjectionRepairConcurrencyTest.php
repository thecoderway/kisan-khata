<?php

namespace Tests\Feature\Financial;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Modules\Financial\Application\ReconciliationService;
use App\Modules\Financial\Application\SalePostingService;
use App\Tenancy\CurrentShop;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('mysql-locking')]
class ProjectionRepairConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_projection_repair_waits_for_posting_then_uses_the_committed_ledger_snapshot(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('This concurrency regression requires MySQL and pcntl.');
        }

        $shop = Shop::factory()->create(['timezone' => 'Asia/Kolkata']);
        $owner = User::factory()->create([
            'shop_id' => $shop->getKey(),
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);
        app(CurrentShop::class)->set($shop);

        $customerId = DB::table('customers')->insertGetId([
            'shop_id' => $shop->getKey(),
            'name_original' => 'Concurrency Customer',
            'name_original_language' => 'en',
            'name_en' => 'Concurrency Customer',
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

        $sales = app(SalePostingService::class);
        $sales->post(
            $owner,
            $customer,
            Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-11-01'),
            '100.00',
            'Initial concurrency fixture',
            'projection-concurrency-initial',
        );

        $account = CustomerAccount::query()->where('customer_id', $customerId)->firstOrFail();
        $account->forceFill([
            'principal_balance' => '70.00',
            'total_payable' => '70.00',
        ])->save();

        // Commit the second posting only after the repair worker is confirmed
        // waiting. Fork after this transaction owns the customer/account lock.
        $primary = DB::connection();
        $primary->beginTransaction();
        $sales->post(
            $owner,
            $customer,
            Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-11-02'),
            '50.00',
            'Concurrent posting held open for repair',
            'projection-concurrency-second',
        );

        $workerConnectionName = 'projection_repair_worker';
        $workerConnectionConfig = config('database.connections.'.DB::getDefaultConnection());

        [$parentSocket, $childSocket] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $pid = pcntl_fork();

        if ($pid === -1) {
            $primary->rollBack();
            fclose($parentSocket);
            fclose($childSocket);
            $this->fail('Unable to fork the MySQL concurrency worker.');
        }

        if ($pid === 0) {
            fclose($parentSocket);

            try {
                // Keep the inherited parent PDO untouched: create a named,
                // independent MySQL session for all worker-side service queries.
                config([
                    'database.default' => $workerConnectionName,
                    "database.connections.{$workerConnectionName}" => $workerConnectionConfig,
                ]);
                DB::setDefaultConnection($workerConnectionName);
                DB::purge($workerConnectionName);
                DB::connection($workerConnectionName);
                $connectionId = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
                fwrite($childSocket, $connectionId."\n");

                app(CurrentShop::class)->set(Shop::query()->findOrFail($shop->getKey()));
                $childCustomer = Customer::query()->findOrFail($customerId);
                $result = app(ReconciliationService::class)->rebuildAndReconcile($childCustomer);

                fwrite($childSocket, json_encode($result, JSON_THROW_ON_ERROR)."\n");
                fclose($childSocket);
                exit(0);
            } catch (\Throwable $exception) {
                fwrite($childSocket, json_encode(['error' => $exception->getMessage()])."\n");
                fclose($childSocket);
                exit(1);
            }
        }

        fclose($childSocket);
        $repairResult = null;

        try {
            $repairConnectionId = (int) trim((string) fgets($parentSocket));
            $deadline = microtime(true) + 10;
            $observedLockWait = false;

            do {
                $waiting = DB::selectOne(
                    'SELECT COUNT(*) AS aggregate
                     FROM information_schema.innodb_trx
                     WHERE trx_mysql_thread_id = ?
                       AND trx_state = ?',
                    [$repairConnectionId, 'LOCK WAIT'],
                );
                if ((int) $waiting->aggregate > 0) {
                    $observedLockWait = true;
                    break;
                }

                usleep(20_000);
            } while (microtime(true) < $deadline);

            $this->assertTrue(
                $observedLockWait,
                'The repair connection must be observed waiting on the posting transaction lock before the posting commits.',
            );

            $primary->commit();

            $line = fgets($parentSocket);
            $repairResult = $line === false ? null : json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            pcntl_waitpid($pid, $status);

            $this->assertIsArray($repairResult);
            $this->assertArrayNotHasKey('error', $repairResult, json_encode($repairResult));
            $this->assertTrue($repairResult['consistent'], json_encode($repairResult['discrepancies']));
            $this->assertSame('150.00', $account->fresh()->principal_balance);
            $this->assertSame('150.00', $account->fresh()->total_payable);
        } finally {
            if ($primary->transactionLevel() > 0) {
                $primary->rollBack();
            }

            fclose($parentSocket);
            if (pcntl_waitpid($pid, $status, WNOHANG) === 0) {
                pcntl_waitpid($pid, $status);
            }
            app(CurrentShop::class)->clear();
        }
    }
}
