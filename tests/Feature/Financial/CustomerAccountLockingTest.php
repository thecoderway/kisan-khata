<?php

namespace Tests\Feature\Financial;

use App\Models\Customer;
use App\Models\Shop;
use App\Models\User;
use App\Modules\Financial\Application\CustomerAccountLockService;
use App\Tenancy\CurrentShop;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('mysql-locking')]
class CustomerAccountLockingTest extends TestCase
{
    use DatabaseMigrations;

    public function test_customer_account_row_lock_blocks_a_second_mysql_connection_until_transaction_ends(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('This integration test requires MySQL/InnoDB row-level FOR UPDATE locks.');
        }

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
            'created_by' => $owner->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $customer = Customer::query()->findOrFail($customerId);
        $lockService = app(CustomerAccountLockService::class);
        $account = DB::transaction(fn () => $lockService->lockForCustomer($customer));

        $connectionName = 'customer_account_lock_probe';
        $connectionConfig = config('database.connections.'.DB::getDefaultConnection());

        config(["database.connections.{$connectionName}" => $connectionConfig]);
        DB::purge($connectionName);

        $primary = DB::connection();
        $probe = DB::connection($connectionName);

        try {
            $primary->beginTransaction();
            $lockService->lockForCustomer($customer);

            $probe->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $lockWasBlocked = false;

            try {
                $probe->table('customer_accounts')
                    ->where('id', $account->getKey())
                    ->lockForUpdate()
                    ->first();
            } catch (QueryException) {
                $lockWasBlocked = true;
            }

            $this->assertTrue(
                $lockWasBlocked,
                'A second MySQL connection must wait while the financial posting owns the customer account lock.',
            );

            $primary->rollBack();

            $releasedAccount = $probe->table('customer_accounts')
                ->where('id', $account->getKey())
                ->lockForUpdate()
                ->first();

            $this->assertNotNull($releasedAccount, 'The second connection must acquire the account lock after commit/rollback.');
        } finally {
            if ($primary->transactionLevel() > 0) {
                $primary->rollBack();
            }

            DB::disconnect($connectionName);
            DB::purge($connectionName);
            app(CurrentShop::class)->clear();
        }
    }
}
