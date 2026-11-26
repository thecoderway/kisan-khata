<?php

namespace Tests\Unit\Financial;

use App\Models\Customer;
use App\Models\Shop;
use App\Models\User;
use App\Modules\Financial\Application\InterestPolicyResolver;
use App\Tenancy\CurrentShop;
use DateTimeImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InterestPolicyResolverTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $owner;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create();
        $this->owner = User::factory()->create([
            'shop_id' => $this->shop->getKey(),
            'role' => User::ROLE_OWNER,
        ]);

        app(CurrentShop::class)->set($this->shop);

        $customerId = DB::table('customers')->insertGetId([
            'shop_id' => $this->shop->getKey(),
            'created_by' => $this->owner->getKey(),
            'name_original' => 'Interest Resolver Test Customer',
            'name_original_language' => 'en',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->customer = Customer::withoutGlobalScopes()->findOrFail($customerId);

        DB::table('interest_policies')->insert([
            'id' => 11,
            'shop_id' => $this->shop->getKey(),
            'policy_code' => InterestPolicyResolver::DEFAULT_POLICY_CODE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('interest_policy_versions')->insert([
            'id' => 21,
            'shop_id' => $this->shop->getKey(),
            'interest_policy_id' => 11,
            'version_number' => 1,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'monthly_rate_percent' => '1.25000000',
            'grace_days' => 30,
            'capitalization_enabled' => 0,
            'payment_waterfall' => 'PRINCIPAL_FIRST',
            'annual_settlement_month_1' => 3,
            'annual_settlement_day_1' => 30,
            'annual_settlement_month_2' => 10,
            'annual_settlement_day_2' => 30,
            'created_by' => $this->owner->getKey(),
            'created_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        app(CurrentShop::class)->clear();

        parent::tearDown();
    }

    public function test_it_resolves_customer_overrides_independently_from_shop_defaults(): void
    {
        DB::table('customer_interest_policy_versions')->insert([
            'id' => 31,
            'shop_id' => $this->shop->getKey(),
            'customer_id' => $this->customer->getKey(),
            'version_number' => 1,
            'effective_from' => '2026-02-01',
            'effective_to' => null,
            'monthly_rate_percent' => null,
            'capitalization_enabled' => 1,
            'created_by' => $this->owner->getKey(),
            'created_at' => now(),
        ]);

        $result = app(InterestPolicyResolver::class)->resolve(
            $this->customer,
            new DateTimeImmutable('2026-02-01'),
        );

        $this->assertSame(21, $result['shop_policy_version_id']);
        $this->assertSame(31, $result['customer_policy_version_id']);
        $this->assertSame('1.25000000', $result['monthly_rate_percent']);
        $this->assertTrue($result['capitalization_enabled']);
        $this->assertSame(30, $result['grace_days']);
        $this->assertSame('PRINCIPAL_FIRST', $result['payment_waterfall']);
    }

    public function test_it_inherits_both_settings_when_no_customer_override_is_effective(): void
    {
        $result = app(InterestPolicyResolver::class)->resolve(
            $this->customer,
            new DateTimeImmutable('2026-01-01'),
        );

        $this->assertNull($result['customer_policy_version_id']);
        $this->assertSame('1.25000000', $result['monthly_rate_percent']);
        $this->assertFalse($result['capitalization_enabled']);
    }

    public function test_it_rejects_overlapping_shop_policy_versions(): void
    {
        DB::table('interest_policy_versions')->insert([
            'id' => 22,
            'shop_id' => $this->shop->getKey(),
            'interest_policy_id' => 11,
            'version_number' => 2,
            'effective_from' => '2026-02-01',
            'effective_to' => null,
            'monthly_rate_percent' => '2.00000000',
            'grace_days' => 30,
            'capitalization_enabled' => 0,
            'payment_waterfall' => 'INTEREST_FIRST',
            'annual_settlement_month_1' => 3,
            'annual_settlement_day_1' => 30,
            'annual_settlement_month_2' => 10,
            'annual_settlement_day_2' => 30,
            'created_by' => $this->owner->getKey(),
            'created_at' => now(),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Multiple shop interest policy versions overlap');

        app(InterestPolicyResolver::class)->resolve(
            $this->customer,
            new DateTimeImmutable('2026-02-01'),
        );
    }
}
