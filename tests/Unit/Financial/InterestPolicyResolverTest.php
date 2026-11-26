<?php

namespace Tests\\Unit\\Financial;

use App\\Models\\Customer;
use App\\Models\\Shop;
use App\\Modules\\Financial\\Application\\InterestPolicyResolver;
use App\\Tenancy\\CurrentShop;
use DateTimeImmutable;
use DomainException;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;
use Tests\\TestCase;

class InterestPolicyResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('interest_policies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('policy_code', 40);
        });

        Schema::create('interest_policy_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('interest_policy_id');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('monthly_rate_percent', 12, 8);
            $table->unsignedSmallInteger('grace_days');
            $table->boolean('capitalization_enabled');
            $table->string('payment_waterfall', 20);
            $table->unsignedTinyInteger('annual_settlement_month_1');
            $table->unsignedTinyInteger('annual_settlement_day_1');
            $table->unsignedTinyInteger('annual_settlement_month_2');
            $table->unsignedTinyInteger('annual_settlement_day_2');
        });

        Schema::create('customer_interest_policy_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('customer_id');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('monthly_rate_percent', 12, 8)->nullable();
            $table->boolean('capitalization_enabled')->nullable();
        });

        $shop = new Shop;
        $shop->setRawAttributes(['id' => 7], true);
        app(CurrentShop::class)->set($shop);

        DB::table('interest_policies')->insert([
            'id' => 11,
            'shop_id' => 7,
            'policy_code' => InterestPolicyResolver::DEFAULT_POLICY_CODE,
        ]);

        DB::table('interest_policy_versions')->insert([
            'id' => 21,
            'shop_id' => 7,
            'interest_policy_id' => 11,
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
            'shop_id' => 7,
            'customer_id' => 41,
            'effective_from' => '2026-02-01',
            'effective_to' => null,
            'monthly_rate_percent' => null,
            'capitalization_enabled' => 1,
        ]);

        $result = app(InterestPolicyResolver::class)->resolve(
            $this->customer(),
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
            $this->customer(),
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
            'shop_id' => 7,
            'interest_policy_id' => 11,
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
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Multiple shop interest policy versions overlap');

        app(InterestPolicyResolver::class)->resolve(
            $this->customer(),
            new DateTimeImmutable('2026-02-01'),
        );
    }

    private function customer(): Customer
    {
        $customer = new Customer;
        $customer->setRawAttributes(['id' => 41, 'shop_id' => 7], true);

        return $customer;
    }
}
