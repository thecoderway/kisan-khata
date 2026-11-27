<?php

namespace Tests\Feature\Seeders;

use App\Models\Customer;
use App\Models\Shop;
use App\Modules\Financial\Application\ReconciliationService;
use App\Tenancy\CurrentShop;
use Database\Seeders\DemoMaaBijasanShopSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoMaaBijasanShopSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.env' => 'testing']);
        putenv('DEMO_SHOP_OWNER_PASSWORD=local-demo-password');
        $_ENV['DEMO_SHOP_OWNER_PASSWORD'] = 'local-demo-password';
    }

    protected function tearDown(): void
    {
        putenv('DEMO_SHOP_OWNER_PASSWORD');
        unset($_ENV['DEMO_SHOP_OWNER_PASSWORD']);

        parent::tearDown();
    }

    public function test_demo_seeder_creates_isolated_shop_and_reconciled_financial_data(): void
    {
        $existingShop = Shop::factory()->create();
        $existingCustomerCount = DB::table('customers')->count();

        $this->seed(DemoMaaBijasanShopSeeder::class);

        $demoShop = Shop::query()
            ->where('name', 'Maa Bijasan Krishi Sewa Kendra')
            ->where('email', 'demo-maa-bijasan@kisan-khata.test')
            ->firstOrFail();

        $this->assertNotSame((int) $existingShop->getKey(), (int) $demoShop->getKey());
        $this->assertSame($existingCustomerCount, DB::table('customers')->where('shop_id', $existingShop->getKey())->count());
        $this->assertSame(40, DB::table('customers')->where('shop_id', $demoShop->getKey())->count());
        $this->assertSame(80, DB::table('sales')->where('shop_id', $demoShop->getKey())->count());
        $this->assertSame(80, DB::table('payments')->where('shop_id', $demoShop->getKey())->count());
        $this->assertDatabaseHas('interest_policies', [
            'shop_id' => $demoShop->getKey(),
            'policy_code' => 'DEFAULT',
        ]);
        $this->assertDatabaseHas('interest_policy_versions', [
            'shop_id' => $demoShop->getKey(),
            'monthly_rate_percent' => '1.50000000',
            'grace_days' => 30,
            'payment_waterfall' => 'PRINCIPAL_FIRST',
            'capitalization_enabled' => 0,
        ]);

        $owner = DB::table('users')->where('shop_id', $demoShop->getKey())->first();
        $this->assertNotNull($owner);
        $this->assertSame('owner', $owner->role);
        $this->assertSame(1, (int) $owner->must_change_password);

        $currentShop = app(CurrentShop::class);
        $previousShop = $currentShop->get();
        $currentShop->set($demoShop);

        try {
            foreach (Customer::query()->orderBy('id')->get() as $customer) {
                $report = app(ReconciliationService::class)->reconcileCustomer($customer);
                $this->assertTrue($report['consistent'], json_encode($report['discrepancies']));
            }
        } finally {
            if ($previousShop !== null) {
                $currentShop->set($previousShop);
            } else {
                $currentShop->clear();
            }
        }
    }

    public function test_running_demo_seeder_twice_does_not_duplicate_data(): void
    {
        $this->seed(DemoMaaBijasanShopSeeder::class);
        $this->seed(DemoMaaBijasanShopSeeder::class);

        $shop = Shop::query()
            ->where('name', 'Maa Bijasan Krishi Sewa Kendra')
            ->where('email', 'demo-maa-bijasan@kisan-khata.test')
            ->firstOrFail();

        $this->assertSame(1, Shop::query()
            ->where('name', 'Maa Bijasan Krishi Sewa Kendra')
            ->where('email', 'demo-maa-bijasan@kisan-khata.test')
            ->count());
        $this->assertSame(40, DB::table('customers')->where('shop_id', $shop->getKey())->count());
        $this->assertSame(80, DB::table('sales')->where('shop_id', $shop->getKey())->count());
        $this->assertSame(80, DB::table('payments')->where('shop_id', $shop->getKey())->count());
    }
}
