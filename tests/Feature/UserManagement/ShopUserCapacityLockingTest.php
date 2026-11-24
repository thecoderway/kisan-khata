<?php

namespace Tests\Feature\UserManagement;

use App\Models\Shop;
use App\Modules\Users\Application\ShopUserCapacity;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShopUserCapacityLockingTest extends TestCase
{
    use DatabaseMigrations;

    public function test_shop_row_lock_blocks_a_second_mysql_connection_until_transaction_ends(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('This integration test requires MySQL/InnoDB; SQLite does not implement row-level FOR UPDATE locks.');
        }

        $shop = Shop::factory()->create();
        $connectionName = 'capacity_lock_probe';
        $connectionConfig = config('database.connections.'.DB::getDefaultConnection());

        config(["database.connections.{$connectionName}" => $connectionConfig]);
        DB::purge($connectionName);

        $primary = DB::connection();
        $probe = DB::connection($connectionName);

        try {
            $primary->beginTransaction();

            app(ShopUserCapacity::class)->lockActiveShop($shop->id);

            $probe->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $lockWasBlocked = false;

            try {
                $probe->table('shops')
                    ->where('id', $shop->id)
                    ->lockForUpdate()
                    ->first();
            } catch (QueryException) {
                $lockWasBlocked = true;
            }

            $this->assertTrue(
                $lockWasBlocked,
                'A second MySQL connection must wait/fail while the first transaction owns the shop row lock.',
            );

            $primary->rollBack();

            $releasedRow = $probe->table('shops')
                ->where('id', $shop->id)
                ->lockForUpdate()
                ->first();

            $this->assertNotNull($releasedRow, 'The second connection must acquire the lock after the first transaction ends.');
        } finally {
            if ($primary->transactionLevel() > 0) {
                $primary->rollBack();
            }

            DB::disconnect($connectionName);
            $shop->delete();
        }
    }
}
