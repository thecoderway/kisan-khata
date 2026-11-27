<?php

namespace Tests\Feature\Financial;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Shop;
use App\Models\User;
use App\Modules\Financial\Application\PaymentPostingService;
use App\Modules\Financial\Application\ReconciliationService;
use App\Modules\Financial\Application\SalePostingService;
use App\Tenancy\CurrentShop;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Batch7FinancialWorkspaceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_all_customer_ledgers_inside_own_shop_but_not_other_shops(): void
    {
        [$shop, $owner, $first] = $this->context();
        $secondId = DB::table('customers')->insertGetId([
            'shop_id' => $shop->getKey(), 'name_original' => 'Second customer',
            'name_original_language' => 'en', 'name_en' => 'Second customer',
            'status' => Customer::STATUS_ACTIVE, 'created_by' => $owner->getKey(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        [, , $foreign] = $this->context();

        app(CurrentShop::class)->set($shop);
        $this->createPolicy($shop, $owner);
        $sale = app(SalePostingService::class)->post(
            $owner, $first, 'UNPAID', CarbonImmutable::parse('2026-11-20'),
            '100.00', 'Shop-wide Staff read test', 'batch-7-staff-sale',
        );
        $payment = app(PaymentPostingService::class)->post(
            $owner, $first, '20.00', CarbonImmutable::parse('2026-11-21'),
            'CASH', null, 'Shop-wide Staff read test', 'batch-7-staff-payment',
        );

        $staff = User::factory()->create([
            'shop_id' => $shop->getKey(), 'role' => User::ROLE_STAFF, 'is_active' => true,
        ]);
        Sanctum::actingAs($staff, ['app']);

        $this->getJson('/api/v1/customers/'.$first->getKey().'/ledger')->assertOk()
            ->assertJsonFragment(['sale_id' => $sale->getKey()]);
        $this->getJson('/api/v1/customers/'.$secondId.'/ledger')->assertOk();
        $this->getJson('/api/v1/customers/'.$foreign->getKey().'/ledger')->assertNotFound();
        $this->getJson('/api/v1/sales')->assertOk()->assertJsonFragment(['sale_number' => $sale->sale_number]);
        $this->getJson('/api/v1/payments')->assertOk()->assertJsonFragment(['payment_number' => $payment->payment_number]);
    }

    public function test_first_party_web_session_can_use_stateful_financial_api(): void
    {
        [$shop, $owner] = $this->context();
        $this->actingAs($owner)
            ->withHeader('Origin', 'http://localhost')
            ->getJson('/api/v1/sales')
            ->assertOk();
    }

    public function test_staff_can_post_sales_and_payments_through_versioned_api(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);
        $staff = User::factory()->create([
            'shop_id' => $shop->getKey(), 'role' => User::ROLE_STAFF, 'is_active' => true,
        ]);
        Sanctum::actingAs($staff, ['app']);

        $this->postJson('/api/v1/sales', [
            'customer_id' => $customer->getKey(),
            'payment_choice' => 'UNPAID',
            'sale_date' => '2026-11-20',
            'amount' => '100.00',
            'description' => 'Staff API sale',
        ], ['Idempotency-Key' => 'batch-7-staff-api-sale'])
            ->assertCreated()->assertJsonPath('data.amount', '100.00');

        $this->postJson('/api/v1/payments', [
            'customer_id' => $customer->getKey(),
            'payment_date' => '2026-11-21',
            'amount' => '25.00',
            'payment_method' => 'CASH',
        ], ['Idempotency-Key' => 'batch-7-staff-api-payment'])
            ->assertCreated()->assertJsonPath('data.amount', '25.00');
    }

    public function test_only_owner_can_revise_a_posted_sale_through_api(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);
        $sale = app(SalePostingService::class)->post(
            $owner, $customer, 'UNPAID', CarbonImmutable::parse('2026-11-20'),
            '100.00', 'Original description', 'batch-7-owner-revision-sale',
        );
        $staff = User::factory()->create([
            'shop_id' => $shop->getKey(), 'role' => User::ROLE_STAFF, 'is_active' => true,
        ]);

        Sanctum::actingAs($staff, ['app']);
        $this->postJson('/api/v1/sales/'.$sale->getKey().'/revision', [
            'description' => 'Unauthorized revision', 'reason' => 'Attempt by Staff',
        ], ['Idempotency-Key' => 'batch-7-staff-sale-revision'])->assertForbidden();

        Sanctum::actingAs($owner, ['app']);
        $this->postJson('/api/v1/sales/'.$sale->getKey().'/revision', [
            'description' => 'Owner revised description', 'reason' => 'Correcting the sale note',
        ], ['Idempotency-Key' => 'batch-7-owner-sale-revision'])
            ->assertCreated()->assertJsonPath('data.new_values.description', 'Owner revised description');

        $this->assertDatabaseHas('audit_logs', [
            'shop_id' => $shop->getKey(), 'action' => 'financial.sale_revised',
            'entity_type' => 'sale_revision',
        ]);
    }

    public function test_sale_revision_validation_and_audit_api_preserve_revision_details(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);
        $sale = app(SalePostingService::class)->post(
            $owner, $customer, 'UNPAID', CarbonImmutable::parse('2026-11-20'),
            '100.00', 'Original description', 'batch-6b-validation-sale',
        );

        Sanctum::actingAs($owner, ['app']);

        $this->postJson('/api/v1/sales/'.$sale->getKey().'/revision', [
            'description' => 'Whitespace reason should fail', 'reason' => '   ',
        ], ['Idempotency-Key' => 'batch-6b-whitespace-reason'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->postJson('/api/v1/sales/'.$sale->getKey().'/revision', [
            'description' => 'Invalid reason type should fail', 'reason' => ['not', 'a', 'string'],
        ], ['Idempotency-Key' => 'batch-6b-invalid-reason-type'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->postJson('/api/v1/sales/'.$sale->getKey().'/revision', [
            'description' => 'Original description', 'reason' => 'No actual change',
        ], ['Idempotency-Key' => 'batch-6b-unchanged-description'])
            ->assertStatus(409);

        $revision = $this->postJson('/api/v1/sales/'.$sale->getKey().'/revision', [
            'description' => 'Corrected description', 'reason' => 'Correcting the original entry',
        ], ['Idempotency-Key' => 'batch-6b-audit-api'])
            ->assertCreated()
            ->assertJsonPath('data.old_values.description', 'Original description')
            ->assertJsonPath('data.new_values.description', 'Corrected description')
            ->assertJsonPath('data.correction_reason', 'Correcting the original entry')
            ->json('data');

        $audit = $this->getJson('/api/v1/financial-audit?action=financial.sale_revised')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'financial.sale_revised')
            ->assertJsonPath('data.0.entity_type', 'sale_revision')
            ->assertJsonPath('data.0.entity_id', $revision['id'])
            ->assertJsonPath('data.0.user.id', $owner->getKey())
            ->assertJsonPath('data.0.reason', 'Correcting the original entry')
            ->assertJsonPath('data.0.old_values.description', 'Original description')
            ->assertJsonPath('data.0.new_values.description', 'Corrected description');

        $this->assertNotEmpty($audit->json('data.0.created_at'));
    }

    public function test_manager_inactive_owner_and_foreign_shop_cannot_revise_sales(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);
        $sale = app(SalePostingService::class)->post(
            $owner, $customer, 'UNPAID', CarbonImmutable::parse('2026-11-20'),
            '100.00', 'Protected sale', 'batch-6b-authorization-sale',
        );

        $manager = User::factory()->create([
            'shop_id' => $shop->getKey(), 'role' => User::ROLE_MANAGER, 'is_active' => true,
        ]);
        Sanctum::actingAs($manager, ['app']);
        $this->postJson('/api/v1/sales/'.$sale->getKey().'/revision', [
            'description' => 'Manager edit', 'reason' => 'Manager is not authorized',
        ], ['Idempotency-Key' => 'batch-6b-manager-revision'])->assertForbidden();

        DB::table('users')->where('id', $owner->getKey())->update(['is_active' => false]);
        $owner->refresh();
        Sanctum::actingAs($owner, ['app']);
        $this->postJson('/api/v1/sales/'.$sale->getKey().'/revision', [
            'description' => 'Inactive Owner edit', 'reason' => 'Inactive Owner is not authorized',
        ], ['Idempotency-Key' => 'batch-6b-inactive-owner-revision'])->assertForbidden();

        DB::table('users')->where('id', $owner->getKey())->update(['is_active' => true]);
        $owner->refresh();

        [$foreignShop, $foreignOwner, $foreignCustomer] = $this->context();
        $this->createPolicy($foreignShop, $foreignOwner);
        $foreignSale = app(SalePostingService::class)->post(
            $foreignOwner, $foreignCustomer, 'UNPAID', CarbonImmutable::parse('2026-11-20'),
            '75.00', 'Foreign shop sale', 'batch-6b-foreign-sale',
        );

        Sanctum::actingAs($owner, ['app']);
        $this->postJson('/api/v1/sales/'.$foreignSale->getKey().'/revision', [
            'description' => 'Cross-shop edit', 'reason' => 'Cross-shop access must fail',
        ], ['Idempotency-Key' => 'batch-6b-foreign-revision'])->assertNotFound();
        $this->getJson('/api/v1/sales/'.$foreignSale->getKey())->assertNotFound();
    }

    public function test_owner_can_create_effective_dated_shop_policy_and_audit_event(): void
    {
        [$shop, $owner] = $this->context();
        $this->createPolicy($shop, $owner);
        Sanctum::actingAs($owner, ['app']);

        $effectiveFrom = now()->addDays(5)->toDateString();
        $this->postJson('/api/v1/interest-policy', [
            'effective_from' => $effectiveFrom,
            'reason' => 'Owner approved updated shop policy',
            'monthly_rate_percent' => '1.25000000',
            'grace_days' => 30,
            'capitalization_enabled' => false,
            'payment_waterfall' => 'PRINCIPAL_FIRST',
            'annual_settlement_month_1' => 3,
            'annual_settlement_day_1' => 30,
            'annual_settlement_month_2' => 10,
            'annual_settlement_day_2' => 30,
        ])->assertCreated()->assertJsonPath('data.version_number', 2);

        $this->assertDatabaseHas('interest_policy_versions', [
            'shop_id' => $shop->getKey(), 'version_number' => 1, 'effective_to' => $effectiveFrom,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'shop_id' => $shop->getKey(), 'user_id' => $owner->getKey(),
            'action' => 'financial.shop_interest_policy_updated',
        ]);
    }

    public function test_owner_can_configure_first_shop_policy_for_legacy_shop_without_policy_row(): void
    {
        [$shop, $owner] = $this->context();
        Sanctum::actingAs($owner, ['app']);

        $this->getJson('/api/v1/interest-policy')
            ->assertOk()
            ->assertExactJson(['data' => []]);

        $effectiveFrom = now()->toDateString();
        $this->postJson('/api/v1/interest-policy', [
            'effective_from' => $effectiveFrom,
            'reason' => 'Initial policy setup for legacy shop',
            'monthly_rate_percent' => '1.50000000',
            'grace_days' => 30,
            'capitalization_enabled' => false,
            'payment_waterfall' => 'PRINCIPAL_FIRST',
            'annual_settlement_month_1' => 3,
            'annual_settlement_day_1' => 30,
            'annual_settlement_month_2' => 10,
            'annual_settlement_day_2' => 30,
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.version_number', 1);

        $this->assertDatabaseHas('interest_policies', [
            'shop_id' => $shop->getKey(),
            'policy_code' => 'DEFAULT',
        ]);
        $this->assertDatabaseHas('interest_policy_versions', [
            'shop_id' => $shop->getKey(),
            'version_number' => 1,
            'effective_from' => $effectiveFrom,
            'monthly_rate_percent' => '1.50000000',
        ]);

        $this->getJson('/api/v1/interest-policy')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_staff_cannot_change_interest_policy_or_view_financial_audit(): void
    {
        [$shop, $owner] = $this->context();
        $this->createPolicy($shop, $owner);
        Sanctum::actingAs($owner, ['app']);
        $this->getJson('/api/v1/financial-audit?per_page=10')
            ->assertOk()->assertJsonPath('meta.per_page', 10);
        $this->getJson('/api/v1/financial-audit?per_page=15')->assertUnprocessable();

        $staff = User::factory()->create([
            'shop_id' => $shop->getKey(), 'role' => User::ROLE_STAFF, 'is_active' => true,
        ]);
        Sanctum::actingAs($staff, ['app']);

        $this->getJson('/api/v1/interest-policy')->assertForbidden();
        $this->postJson('/api/v1/interest-policy', [])->assertForbidden();
        $this->getJson('/api/v1/financial-audit')->assertForbidden();
    }

    public function test_owner_projection_repair_requires_reason_and_writes_audit_in_same_operation(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);
        Sanctum::actingAs($owner, ['app']);

        app(SalePostingService::class)->post(
            $owner, $customer, 'UNPAID', CarbonImmutable::parse('2026-11-01'),
            '100.00', 'Batch 7 repair test', 'batch-7-repair-test',
        );
        DB::table('customer_accounts')->where('shop_id', $shop->getKey())
            ->where('customer_id', $customer->getKey())
            ->update(['principal_balance' => '90.00', 'total_payable' => '90.00']);

        $this->postJson('/api/v1/reconciliation/customers/'.$customer->getKey().'/repair', [])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->postJson('/api/v1/reconciliation/customers/'.$customer->getKey().'/repair', [
            'reason' => 'Repair verified projection mismatch',
        ])->assertOk()->assertJsonPath('data.consistent', true);

        $this->assertDatabaseHas('customer_accounts', [
            'shop_id' => $shop->getKey(), 'customer_id' => $customer->getKey(),
            'principal_balance' => '100.00', 'total_payable' => '100.00',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'shop_id' => $shop->getKey(), 'user_id' => $owner->getKey(),
            'action' => 'financial.customer_projection_repaired',
            'entity_type' => 'customer_projection', 'entity_id' => $customer->getKey(),
            'reason' => 'Repair verified projection mismatch',
        ]);
    }

    public function test_projection_repair_rolls_back_projection_and_audit_when_audit_callback_fails(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);

        app(SalePostingService::class)->post(
            $owner, $customer, 'UNPAID', CarbonImmutable::parse('2026-11-01'),
            '100.00', 'Repair rollback test', 'batch-8-5-repair-rollback',
        );

        DB::table('customer_accounts')->where('shop_id', $shop->getKey())
            ->where('customer_id', $customer->getKey())
            ->update(['principal_balance' => '90.00', 'total_payable' => '90.00']);

        try {
            app(ReconciliationService::class)->repairAndReconcile(
                $customer,
                function (array $before, array $after) use ($owner, $customer): void {
                    AuditLog::query()->create([
                        'user_id' => $owner->getKey(),
                        'action' => 'financial.customer_projection_repaired',
                        'entity_type' => 'customer_projection',
                        'entity_id' => $customer->getKey(),
                        'reason' => 'Rollback audit failure regression',
                        'old_values' => ['consistent' => $before['consistent']],
                        'new_values' => ['consistent' => $after['consistent']],
                        'ip_address' => '127.0.0.1',
                        'user_agent' => 'Batch 8.5 regression',
                    ]);

                    throw new \RuntimeException('Simulated audit persistence failure.');
                },
            );

            $this->fail('The simulated audit failure should abort the repair transaction.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated audit persistence failure.', $exception->getMessage());
        }

        $this->assertDatabaseHas('customer_accounts', [
            'shop_id' => $shop->getKey(),
            'customer_id' => $customer->getKey(),
            'principal_balance' => '90.00',
            'total_payable' => '90.00',
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'shop_id' => $shop->getKey(),
            'action' => 'financial.customer_projection_repaired',
            'entity_type' => 'customer_projection',
            'entity_id' => $customer->getKey(),
            'reason' => 'Rollback audit failure regression',
        ]);
    }

    public function test_only_owner_can_repair_reconciliation_and_foreign_shop_customer_is_hidden(): void
    {
        [$shop, $owner, $customer] = $this->context();
        [, , $foreignCustomer] = $this->context();
        app(CurrentShop::class)->set($shop);

        $manager = User::factory()->create([
            'shop_id' => $shop->getKey(), 'role' => User::ROLE_MANAGER, 'is_active' => true,
        ]);
        $staff = User::factory()->create([
            'shop_id' => $shop->getKey(), 'role' => User::ROLE_STAFF, 'is_active' => true,
        ]);
        $url = '/api/v1/reconciliation/customers/'.$customer->getKey().'/repair';
        $payload = ['reason' => 'Authorization regression coverage'];

        Sanctum::actingAs($manager, ['app']);
        $this->postJson($url, $payload)->assertForbidden();

        Sanctum::actingAs($staff, ['app']);
        $this->postJson($url, $payload)->assertForbidden();

        Sanctum::actingAs($owner, ['app']);
        $this->postJson('/api/v1/reconciliation/customers/'.$foreignCustomer->getKey().'/repair', $payload)
            ->assertNotFound();
    }

    public function test_shop_policy_rejects_impossible_annual_settlement_dates_but_accepts_february_29(): void
    {
        [$shop, $owner] = $this->context();
        $this->createPolicy($shop, $owner);
        Sanctum::actingAs($owner, ['app']);

        $payload = [
            'effective_from' => now()->addDays(5)->toDateString(),
            'reason' => 'Owner approved calendar validation',
            'monthly_rate_percent' => '1.25000000',
            'grace_days' => 30,
            'capitalization_enabled' => false,
            'payment_waterfall' => 'PRINCIPAL_FIRST',
            'annual_settlement_month_1' => 4,
            'annual_settlement_day_1' => 31,
            'annual_settlement_month_2' => 10,
            'annual_settlement_day_2' => 30,
        ];

        $this->postJson('/api/v1/interest-policy', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('annual_settlement_day_1');

        $payload['annual_settlement_month_1'] = 2;
        $payload['annual_settlement_day_1'] = 29;

        $this->postJson('/api/v1/interest-policy', $payload)
            ->assertCreated()
            ->assertJsonPath('data.annual_settlement_month_1', 2)
            ->assertJsonPath('data.annual_settlement_day_1', 29);
    }

    public function test_sales_and_payments_listings_support_server_side_search_filters_and_page_sizes(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);
        Sanctum::actingAs($owner, ['app']);

        for ($index = 1; $index <= 11; $index++) {
            app(SalePostingService::class)->post(
                $owner,
                $customer,
                'UNPAID',
                CarbonImmutable::parse('2026-11-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT)),
                '100.00',
                'Searchable financial listing sale '.$index,
                'financial-ui-sale-'.$index,
            );
        }

        $firstPage = $this->getJson('/api/v1/sales?per_page=10&page=1')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 11);
        $this->assertCount(10, $firstPage->json('data'));

        $secondPage = $this->getJson('/api/v1/sales?per_page=10&page=2')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2);
        $this->assertCount(1, $secondPage->json('data'));

        $this->getJson('/api/v1/sales?q=Batch%20Seven%20Customer&per_page=20')
            ->assertOk()
            ->assertJsonPath('meta.total', 11);

        $this->getJson('/api/v1/sales?per_page=101')->assertUnprocessable();
        $this->getJson('/api/v1/sales?page=0')->assertUnprocessable();

        // API middleware clears the request-scoped tenant context after each request.
        // Restore the explicit shop context before invoking the posting service directly.
        app(CurrentShop::class)->set($shop);

        app(PaymentPostingService::class)->post(
            $owner,
            $customer,
            '25.00',
            CarbonImmutable::parse('2026-11-25'),
            'UPI',
            'FIN-SEARCH-REF',
            'Searchable payment listing',
            'financial-ui-payment-search',
        );

        $this->getJson('/api/v1/payments?q=FIN-SEARCH-REF&payment_method=UPI&per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonFragment(['reference_number' => 'FIN-SEARCH-REF']);
    }

    public function test_customer_directory_modes_separate_transaction_policy_and_financial_history_visibility(): void
    {
        [$shop, $owner, $active] = $this->context();
        $this->createPolicy($shop, $owner);
        app(CurrentShop::class)->set($shop);

        $inactiveId = DB::table('customers')->insertGetId([
            'shop_id' => $shop->getKey(), 'name_original' => 'Inactive history customer',
            'name_original_language' => 'en', 'name_en' => 'Inactive history customer',
            'status' => Customer::STATUS_ACTIVE, 'created_by' => $owner->getKey(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $deletedId = DB::table('customers')->insertGetId([
            'shop_id' => $shop->getKey(), 'name_original' => 'Deleted legacy customer',
            'name_original_language' => 'en', 'name_en' => 'Deleted legacy customer',
            'status' => Customer::STATUS_ACTIVE, 'created_by' => $owner->getKey(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        app(SalePostingService::class)->post(
            $owner, Customer::query()->findOrFail($inactiveId), 'UNPAID',
            CarbonImmutable::parse('2026-11-20'), '100.00', 'Inactive history',
            'customer-visibility-inactive-sale',
        );
        DB::table('customers')->where('id', $inactiveId)->update(['status' => Customer::STATUS_INACTIVE]);
        app(SalePostingService::class)->post(
            $owner, Customer::query()->findOrFail($deletedId), 'UNPAID',
            CarbonImmutable::parse('2026-11-21'), '75.00', 'Deleted legacy history',
            'customer-visibility-deleted-sale',
        );
        DB::table('customers')->where('id', $deletedId)->update(['deleted_at' => now()]);

        Sanctum::actingAs($owner, ['app']);

        $this->getJson('/api/v1/customers?status=active')
            ->assertOk()
            ->assertJsonFragment(['id' => $active->getKey()])
            ->assertJsonMissing(['id' => $inactiveId])
            ->assertJsonMissing(['id' => $deletedId]);

        $this->getJson('/api/v1/customers?status=all')
            ->assertOk()
            ->assertJsonFragment(['id' => $active->getKey()])
            ->assertJsonFragment(['id' => $inactiveId])
            ->assertJsonMissing(['id' => $deletedId]);

        $this->getJson('/api/v1/customers?status=financial-history')
            ->assertOk()
            ->assertJsonFragment(['id' => $inactiveId])
            ->assertJsonFragment(['id' => $deletedId])
            ->assertJsonMissing(['id' => $active->getKey()]);

        $this->getJson('/api/v1/customers?status=deleted')
            ->assertOk()
            ->assertJsonFragment(['id' => $deletedId]);
    }

    public function test_staff_cannot_browse_soft_deleted_customer_directory_but_can_read_legacy_ledger(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);
        app(SalePostingService::class)->post(
            $owner, $customer, 'UNPAID', CarbonImmutable::parse('2026-11-20'),
            '100.00', 'Legacy deleted customer ledger', 'customer-visibility-deleted-ledger',
        );
        DB::table('customers')->where('id', $customer->getKey())->update(['deleted_at' => now()]);

        $staff = User::factory()->create([
            'shop_id' => $shop->getKey(), 'role' => User::ROLE_STAFF, 'is_active' => true,
        ]);
        Sanctum::actingAs($staff, ['app']);

        $this->getJson('/api/v1/customers?status=deleted')->assertForbidden();
        $this->getJson('/api/v1/customers/'.$customer->getKey().'/ledger')->assertOk();
    }

    public function test_posted_sales_and_payments_keep_soft_deleted_customer_identity(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);
        app(SalePostingService::class)->post(
            $owner, $customer, 'UNPAID', CarbonImmutable::parse('2026-11-20'),
            '100.00', 'Historical customer relationship', 'customer-visibility-history-sale',
        );
        app(PaymentPostingService::class)->post(
            $owner, $customer, '25.00', CarbonImmutable::parse('2026-11-21'),
            'CASH', null, 'Historical customer relationship', 'customer-visibility-history-payment',
        );
        DB::table('customers')->where('id', $customer->getKey())->update(['deleted_at' => now()]);

        Sanctum::actingAs($owner, ['app']);

        $sales = $this->getJson('/api/v1/sales')->assertOk();
        $sales->assertJsonPath('data.0.customer.name_original', $customer->name_original);
        $this->assertNotNull($sales->json('data.0.customer.deleted_at'));

        $payments = $this->getJson('/api/v1/payments')->assertOk();
        $payments->assertJsonPath('data.0.customer.name_original', $customer->name_original);
        $this->assertNotNull($payments->json('data.0.customer.deleted_at'));
    }

    public function test_inactive_customer_cannot_be_used_for_new_sale_or_payment(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);
        app(SalePostingService::class)->post(
            $owner, $customer, 'UNPAID', CarbonImmutable::parse('2026-11-20'),
            '100.00', 'Existing balance before deactivation', 'customer-visibility-inactive-existing-sale',
        );
        DB::table('customers')->where('id', $customer->getKey())
            ->update(['status' => Customer::STATUS_INACTIVE]);

        Sanctum::actingAs($owner, ['app']);

        $this->postJson('/api/v1/sales', [
            'customer_id' => $customer->getKey(),
            'payment_choice' => 'UNPAID',
            'sale_date' => '2026-11-22',
            'amount' => '10.00',
        ], ['Idempotency-Key' => 'customer-visibility-rejected-sale'])->assertStatus(409);

        $this->postJson('/api/v1/payments', [
            'customer_id' => $customer->getKey(),
            'payment_date' => '2026-11-22',
            'amount' => '10.00',
            'payment_method' => 'CASH',
        ], ['Idempotency-Key' => 'customer-visibility-rejected-payment'])->assertStatus(409);
    }

    public function test_sale_details_api_returns_financial_lifecycle_relations(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);
        $sale = app(SalePostingService::class)->post(
            $owner, $customer, 'UNPAID', CarbonImmutable::parse('2026-11-20'),
            '100.00', 'Sale details lifecycle test', 'batch-6a-sale-details-posting',
        );
        app(PaymentPostingService::class)->post(
            $owner, $customer, '25.00', CarbonImmutable::parse('2026-11-21'),
            'UPI', 'BATCH-6A-DETAILS-REF', 'Partial payment for details test',
            'batch-6a-sale-details-payment',
        );

        Sanctum::actingAs($owner, ['app']);

        $response = $this->getJson('/api/v1/sales/'.$sale->getKey())
            ->assertOk()
            ->assertJsonPath('data.id', $sale->getKey())
            ->assertJsonPath('data.sale_number', $sale->sale_number)
            ->assertJsonPath('data.customer.id', $customer->getKey())
            ->assertJsonPath('data.payment_status', 'PARTIALLY_PAID')
            ->assertJsonPath('data.financial_transaction.transaction_type', 'SALE_POSTING');

        $this->assertNotEmpty($response->json('data.ledger_entries'));
        $this->assertNotEmpty($response->json('data.allocation_items'));
        $this->assertSame('BATCH-6A-DETAILS-REF', $response->json('data.allocation_items.0.payment.reference_number'));
    }

    public function test_sale_details_are_available_to_shop_roles_and_foreign_shop_sales_are_hidden(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);
        $sale = app(SalePostingService::class)->post(
            $owner, $customer, 'UNPAID', CarbonImmutable::parse('2026-11-20'),
            '100.00', 'Shop-scoped sale details', 'batch-6a-role-sale-details',
        );

        [$foreignShop, $foreignOwner, $foreignCustomer] = $this->context();
        $this->createPolicy($foreignShop, $foreignOwner);
        $foreignSale = app(SalePostingService::class)->post(
            $foreignOwner, $foreignCustomer, 'UNPAID', CarbonImmutable::parse('2026-11-20'),
            '75.00', 'Foreign shop sale details', 'batch-6a-foreign-sale-details',
        );

        foreach ([User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_STAFF] as $role) {
            $actor = $role === User::ROLE_OWNER ? $owner : User::factory()->create([
                'shop_id' => $shop->getKey(), 'role' => $role, 'is_active' => true,
            ]);
            Sanctum::actingAs($actor, ['app']);
            $this->getJson('/api/v1/sales/'.$sale->getKey())->assertOk()
                ->assertJsonPath('data.id', $sale->getKey());
        }

        Sanctum::actingAs($owner, ['app']);
        $this->getJson('/api/v1/sales/'.$foreignSale->getKey())->assertNotFound();
    }

    public function test_payment_details_api_returns_allocation_ledger_transaction_and_correction_history(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);
        $sale = app(SalePostingService::class)->post(
            $owner, $customer, 'UNPAID', CarbonImmutable::parse('2026-11-20'),
            '100.00', 'Payment details linked Sale', 'batch-7a-payment-details-sale',
        );
        $payment = app(PaymentPostingService::class)->post(
            $owner, $customer, '25.00', CarbonImmutable::parse('2026-11-21'),
            'UPI', 'BATCH-7A-DETAILS-REF', 'Payment details lifecycle test',
            'batch-7a-payment-details-posting',
        );

        Sanctum::actingAs($owner, ['app']);

        $response = $this->getJson('/api/v1/payments/'.$payment->getKey())
            ->assertOk()
            ->assertJsonPath('data.id', $payment->getKey())
            ->assertJsonPath('data.payment_number', $payment->payment_number)
            ->assertJsonPath('data.customer.id', $customer->getKey())
            ->assertJsonPath('data.reference_number', 'BATCH-7A-DETAILS-REF')
            ->assertJsonPath('data.financial_transaction.transaction_type', 'PAYMENT_POSTING')
            ->assertJsonPath('data.allocation.allocation_type', 'NORMAL_FIFO')
            ->assertJsonPath('data.allocation.total_allocated', '25.00')
            ->assertJsonPath('data.allocation.items.0.sale.sale_number', $sale->sale_number);

        $this->assertNotEmpty($response->json('data.ledger_entries'));
        $this->assertSame('PRINCIPAL', $response->json('data.allocation.items.0.component_type'));

        $this->postJson('/api/v1/customers/'.$customer->getKey().'/payments/'.$payment->getKey().'/correction', [
            'reason' => 'Correcting the received payment entry',
            'replacement' => [
                'amount' => '25.00',
                'payment_method' => 'UPI',
                'reference_number' => 'BATCH-7A-REPLACEMENT-REF',
                'description' => 'Replacement payment for detail history',
            ],
        ], ['Idempotency-Key' => 'batch-7a-payment-details-correction'])
            ->assertCreated();

        $corrected = $this->getJson('/api/v1/payments/'.$payment->getKey())
            ->assertOk()
            ->assertJsonPath('data.revisions.0.revision_type', 'REVERSAL')
            ->assertJsonPath('data.revisions.0.correction_reason', 'Correcting the received payment entry')
            ->assertJsonPath('data.revisions.1.revision_type', 'REPLACEMENT');

        $this->assertNotEmpty($corrected->json('data.revisions.0.reversal_payment.payment_number'));
        $this->assertNotEmpty($corrected->json('data.revisions.1.replacement_payment.payment_number'));

        $reversalId = $corrected->json('data.revisions.0.reversal_payment.id');
        $replacementId = $corrected->json('data.revisions.1.replacement_payment.id');

        $this->getJson('/api/v1/payments/'.$reversalId)
            ->assertOk()
            ->assertJsonPath('data.reversed_payment.payment_number', $payment->payment_number);
        $this->getJson('/api/v1/payments/'.$replacementId)
            ->assertOk()
            ->assertJsonPath('data.replacement_for_payment.payment_number', $payment->payment_number);
    }

    public function test_payment_details_are_available_to_shop_roles_and_foreign_shop_payments_are_hidden(): void
    {
        [$shop, $owner, $customer] = $this->context();
        $this->createPolicy($shop, $owner);
        $payment = app(PaymentPostingService::class)->post(
            $owner, $customer, '20.00', CarbonImmutable::parse('2026-11-21'),
            'CASH', null, 'Shop-scoped Payment details', 'batch-7a-role-payment-details',
        );

        [$foreignShop, $foreignOwner, $foreignCustomer] = $this->context();
        $this->createPolicy($foreignShop, $foreignOwner);
        $foreignPayment = app(PaymentPostingService::class)->post(
            $foreignOwner, $foreignCustomer, '15.00', CarbonImmutable::parse('2026-11-21'),
            'CASH', null, 'Foreign Shop Payment details', 'batch-7a-foreign-payment-details',
        );

        foreach ([User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_STAFF] as $role) {
            $actor = $role === User::ROLE_OWNER ? $owner : User::factory()->create([
                'shop_id' => $shop->getKey(), 'role' => $role, 'is_active' => true,
            ]);
            Sanctum::actingAs($actor, ['app']);
            $this->getJson('/api/v1/payments/'.$payment->getKey())->assertOk()
                ->assertJsonPath('data.id', $payment->getKey());
        }

        Sanctum::actingAs($owner, ['app']);
        $this->getJson('/api/v1/payments/'.$foreignPayment->getKey())->assertNotFound();
    }

    private function context(): array
    {
        $shop = Shop::factory()->create(['timezone' => 'Asia/Kolkata']);
        $owner = User::factory()->create([
            'shop_id' => $shop->getKey(), 'role' => User::ROLE_OWNER, 'is_active' => true,
        ]);
        app(CurrentShop::class)->set($shop);
        $id = DB::table('customers')->insertGetId([
            'shop_id' => $shop->getKey(), 'name_original' => 'Batch Seven Customer',
            'name_original_language' => 'en', 'name_en' => 'Batch Seven Customer',
            'status' => Customer::STATUS_ACTIVE, 'created_by' => $owner->getKey(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$shop, $owner, Customer::query()->findOrFail($id)];
    }

    private function createPolicy(Shop $shop, User $owner): void
    {
        $policyId = DB::table('interest_policies')->insertGetId([
            'shop_id' => $shop->getKey(), 'policy_code' => 'DEFAULT',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('interest_policy_versions')->insert([
            'shop_id' => $shop->getKey(), 'interest_policy_id' => $policyId,
            'version_number' => 1, 'effective_from' => '2026-01-01', 'effective_to' => null,
            'monthly_rate_percent' => '0.00000000', 'grace_days' => 30,
            'capitalization_enabled' => false, 'payment_waterfall' => 'PRINCIPAL_FIRST',
            'annual_settlement_month_1' => 3, 'annual_settlement_day_1' => 30,
            'annual_settlement_month_2' => 10, 'annual_settlement_day_2' => 30,
            'created_by' => $owner->getKey(), 'created_at' => now(),
        ]);
    }
}
