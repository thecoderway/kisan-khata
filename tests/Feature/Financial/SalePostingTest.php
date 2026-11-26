<?php

namespace Tests\Feature\Financial;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\FinancialTransaction;
use App\Models\IdempotencyKey;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Modules\Financial\Application\FinancialTransactionService;
use App\Modules\Financial\Application\SalePostingService;
use App\Modules\Financial\Application\SaleRevisionService;
use App\Tenancy\CurrentShop;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class SalePostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpaid_sale_posts_full_principal_without_creating_a_payment(): void
    {
        [$shop, $owner, $customer] = $this->makeCustomerContext();

        $sale = app(SalePostingService::class)->post(
            $owner,
            $customer,
            Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-11-01'),
            '1250',
            'Credit sale',
            'sale-unpaid-001',
        );

        $this->assertSame($shop->getKey(), (int) $sale->shop_id);
        $this->assertSame($customer->getKey(), (int) $sale->customer_id);
        $this->assertSame('1250.00', $sale->amount);
        $this->assertSame(Sale::PAYMENT_STATUS_UNPAID, $sale->payment_status);
        $this->assertSame(Sale::PAYMENT_CHOICE_UNPAID, $sale->sale_payment_choice);
        $this->assertSame(Sale::INTEREST_STATUS_PENDING, $sale->interest_settlement_status);
        $this->assertNull($sale->saleDayPayment()->first());
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertDatabaseHas('financial_transactions', [
            'id' => $sale->financial_transaction_id,
            'transaction_type' => FinancialTransaction::TYPE_SALE_POSTING,
        ]);
    }

    public function test_paid_sale_creates_its_own_automatic_payment_atomically(): void
    {
        [$shop, $owner, $customer] = $this->makeCustomerContext();

        $sale = app(SalePostingService::class)->post(
            $owner,
            $customer,
            Sale::PAYMENT_CHOICE_PAID,
            CarbonImmutable::parse('2026-11-01'),
            '2500.50',
            'Paid sale',
            'sale-paid-001',
        );

        $payment = $sale->saleDayPayment()->firstOrFail();

        $this->assertSame(Sale::PAYMENT_STATUS_PAID, $sale->payment_status);
        $this->assertSame(Payment::TYPE_SALE_DAY_AUTO, $payment->payment_type);
        $this->assertSame($sale->getKey(), (int) $payment->source_sale_id);
        $this->assertSame($sale->customer_id, $payment->customer_id);
        $this->assertSame('2500.50', $payment->amount);
        $this->assertSame($sale->sale_date->toDateString(), $payment->payment_date->toDateString());
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('financial_transactions', 2);
        $this->assertDatabaseCount('idempotency_keys', 2);
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseCount('interest_calculation_runs', 0);
        $this->assertDatabaseHas('financial_transactions', [
            'id' => $payment->financial_transaction_id,
            'transaction_type' => FinancialTransaction::TYPE_PAYMENT_POSTING,
            'source_type' => 'sale',
            'source_id' => $sale->getKey(),
        ]);
        $this->assertDatabaseHas('payments', [
            'shop_id' => $shop->getKey(),
            'source_sale_id' => $sale->getKey(),
            'payment_type' => Payment::TYPE_SALE_DAY_AUTO,
            'amount' => '2500.50',
        ]);
    }

    public function test_paid_sale_retry_replays_without_duplicate_sale_or_automatic_payment(): void
    {
        [, $owner, $customer] = $this->makeCustomerContext();
        $service = app(SalePostingService::class);

        $first = $service->post(
            $owner,
            $customer,
            Sale::PAYMENT_CHOICE_PAID,
            CarbonImmutable::parse('2026-11-01'),
            '2500.50',
            'Paid sale',
            'sale-paid-replay-001',
        );

        $replayed = $service->post(
            $owner,
            $customer,
            Sale::PAYMENT_CHOICE_PAID,
            CarbonImmutable::parse('2026-11-01'),
            '2500.50',
            'Paid sale',
            'sale-paid-replay-001',
        );

        $this->assertSame($first->getKey(), $replayed->getKey());
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('financial_transactions', 2);
        $this->assertDatabaseCount('idempotency_keys', 2);
    }

    public function test_sale_posting_rejects_zero_invalid_precision_and_invalid_payment_choice(): void
    {
        [, $owner, $customer] = $this->makeCustomerContext();
        $service = app(SalePostingService::class);

        foreach (['0', '-10', '10.123', '12345678901234'] as $amount) {
            try {
                $service->post(
                    $owner,
                    $customer,
                    Sale::PAYMENT_CHOICE_UNPAID,
                    CarbonImmutable::parse('2026-11-01'),
                    $amount,
                    null,
                    'invalid-amount-'.hash('sha256', $amount),
                );

                $this->fail('Invalid Sale amounts must be rejected.');
            } catch (InvalidArgumentException) {
                $this->assertDatabaseCount('sales', 0);
            }
        }

        $this->expectException(InvalidArgumentException::class);

        $service->post(
            $owner,
            $customer,
            'CREDIT',
            CarbonImmutable::parse('2026-11-01'),
            '100.00',
            null,
            'invalid-choice-001',
        );
    }

    public function test_inactive_customer_cannot_receive_a_new_sale(): void
    {
        [$shop, $owner, $customer] = $this->makeCustomerContext();
        DB::table('customers')->where('id', $customer->getKey())->update(['status' => Customer::STATUS_INACTIVE]);

        try {
            app(SalePostingService::class)->post(
                $owner,
                $customer,
                Sale::PAYMENT_CHOICE_UNPAID,
                CarbonImmutable::parse('2026-11-01'),
                '100.00',
                null,
                'inactive-customer-sale-001',
            );

            $this->fail('Inactive customers must not receive new Sales.');
        } catch (ConflictHttpException) {
            $this->assertDatabaseMissing('customer_accounts', [
                'shop_id' => $shop->getKey(),
                'customer_id' => $customer->getKey(),
            ]);
            $this->assertDatabaseCount('sales', 0);
            $this->assertDatabaseCount('idempotency_keys', 0);
        }
    }

    public function test_owner_can_revise_sale_description_with_immutable_revision_and_audit_history(): void
    {
        [, $owner, $customer] = $this->makeCustomerContext();
        $sale = $this->postUnpaidSale($owner, $customer);
        $service = app(SaleRevisionService::class);

        $revision = $service->reviseDescription(
            $owner,
            $sale,
            'Corrected goods description',
            'Corrected a typing error',
            'sale-revision-001',
        );

        $replayed = $service->reviseDescription(
            $owner,
            $sale,
            'Corrected goods description',
            'Corrected a typing error',
            'sale-revision-001',
        );

        $updatedSale = Sale::query()->findOrFail($sale->getKey());

        $this->assertSame($revision->getKey(), $replayed->getKey());
        $this->assertSame(['description' => 'Credit sale'], $revision->old_values);
        $this->assertSame(['description' => 'Corrected goods description'], $revision->new_values);
        $this->assertSame($owner->getKey(), (int) $revision->changed_by);
        $this->assertSame('Corrected a typing error', $revision->correction_reason);
        $this->assertSame('Corrected goods description', $updatedSale->description);
        $this->assertSame('1250.00', $updatedSale->amount);
        $this->assertDatabaseCount('sale_revisions', 1);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'financial.sale_revised',
            'entity_type' => 'sale_revision',
            'entity_id' => $revision->getKey(),
            'reason' => 'Corrected a typing error',
        ]);
        $this->assertDatabaseHas('idempotency_keys', [
            'operation_type' => 'SALE_REVISION',
            'resource_type' => 'sale_revision',
            'resource_id' => $revision->getKey(),
            'status' => IdempotencyKey::STATUS_COMPLETED,
            'response_code' => 200,
        ]);
    }

    public function test_sale_revision_is_rejected_after_a_later_posted_normal_payment(): void
    {
        [, $owner, $customer] = $this->makeCustomerContext();
        $sale = $this->postUnpaidSale($owner, $customer);
        $this->postNormalPaymentAfterSale($owner, $customer, $sale);

        $this->expectException(ConflictHttpException::class);

        app(SaleRevisionService::class)->reviseDescription(
            $owner,
            $sale,
            'Must be rejected',
            'Attempt after later Payment',
            'sale-revision-locked-payment',
        );
    }

    public function test_sale_revision_is_rejected_after_completed_annual_interest_settlement(): void
    {
        [$shop, $owner, $customer] = $this->makeCustomerContext();
        $sale = $this->postUnpaidSale($owner, $customer);
        $this->createCompletedSettlementRun($shop, $owner, $customer, $sale);

        $this->expectException(ConflictHttpException::class);

        app(SaleRevisionService::class)->reviseDescription(
            $owner,
            $sale,
            'Must be rejected',
            'Attempt after annual settlement',
            'sale-revision-locked-settlement',
        );
    }

    public function test_manager_cannot_initiate_a_posted_sale_revision(): void
    {
        [$shop, $owner, $customer] = $this->makeCustomerContext();
        $sale = $this->postUnpaidSale($owner, $customer);
        $manager = User::factory()->create([
            'shop_id' => $shop->getKey(),
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        $this->expectException(AuthorizationException::class);

        app(SaleRevisionService::class)->reviseDescription(
            $manager,
            $sale,
            'Manager edit',
            'Not authorized',
            'sale-revision-manager-001',
        );
    }

    private function postUnpaidSale(User $owner, Customer $customer): Sale
    {
        return app(SalePostingService::class)->post(
            $owner,
            $customer,
            Sale::PAYMENT_CHOICE_UNPAID,
            CarbonImmutable::parse('2026-11-01'),
            '1250.00',
            'Credit sale',
            'sale-for-revision-'.$customer->getKey(),
        );
    }

    private function postNormalPaymentAfterSale(User $owner, Customer $customer, Sale $sale): Payment
    {
        $transactions = app(FinancialTransactionService::class);
        $paymentDate = CarbonImmutable::parse($sale->sale_date->toDateString())->addDay();
        $postedAt = $sale->posted_at->addSecond();

        $transaction = $transactions->executeForCustomer(
            $owner,
            $customer,
            'PAYMENT_POSTING',
            'normal-payment-after-sale-'.$sale->getKey(),
            ['amount' => '100.00', 'payment_date' => $paymentDate->toDateString()],
            function (CustomerAccount $account, IdempotencyKey $key) use (
                $transactions,
                $owner,
                $customer,
                $paymentDate,
                $postedAt,
            ): FinancialTransaction {
                $transaction = $transactions->createHeader(
                    $owner,
                    $customer,
                    $key,
                    FinancialTransaction::TYPE_PAYMENT_POSTING,
                    $paymentDate,
                );

                $payment = new Payment;
                $payment->forceFill([
                    'shop_id' => $customer->shop_id,
                    'customer_id' => $customer->getKey(),
                    'payment_number' => 'PAY-LOCK-'.uniqid(),
                    'payment_type' => Payment::TYPE_NORMAL,
                    'source_sale_id' => null,
                    'payment_date' => $paymentDate->toDateString(),
                    'amount' => '100.00',
                    'financial_transaction_id' => $transaction->getKey(),
                    'created_by' => $owner->getKey(),
                    'posted_by' => $owner->getKey(),
                    'posted_at' => $postedAt,
                    'created_at' => now(),
                ]);
                $payment->save();

                return $transaction;
            },
        );

        return Payment::query()->where('financial_transaction_id', $transaction->getKey())->firstOrFail();
    }

    private function createCompletedSettlementRun(Shop $shop, User $owner, Customer $customer, Sale $sale): void
    {
        $now = now();
        $policyId = DB::table('interest_policies')->insertGetId([
            'shop_id' => $shop->getKey(),
            'policy_code' => 'DEFAULT-'.uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $policyVersionId = DB::table('interest_policy_versions')->insertGetId([
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
            'created_at' => $now,
        ]);

        $keyId = DB::table('idempotency_keys')->insertGetId([
            'shop_id' => $shop->getKey(),
            'operation_type' => 'ANNUAL_AUTO_SETTLEMENT',
            'key_hash' => hash('sha256', 'annual-settlement-'.$customer->getKey()),
            'request_hash' => hash('sha256', 'annual-settlement-payload-'.$customer->getKey()),
            'status' => IdempotencyKey::STATUS_COMPLETED,
            'resource_type' => 'interest_settlement_run',
            'resource_id' => null,
            'response_code' => 201,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $completedAt = $sale->posted_at->addMinute();

        DB::table('interest_settlement_runs')->insert([
            'shop_id' => $shop->getKey(),
            'customer_id' => $customer->getKey(),
            'settlement_date' => '2026-11-30',
            'cutoff_date_exclusive' => '2026-11-30',
            'shop_policy_version_id' => $policyVersionId,
            'status' => 'COMPLETED',
            'idempotency_key_id' => $keyId,
            'started_at' => $sale->posted_at,
            'completed_at' => $completedAt,
            'created_at' => $now,
        ]);
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
            'status' => Customer::STATUS_ACTIVE,
            'created_by' => $owner->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$shop, $owner, Customer::query()->findOrFail($customerId)];
    }
}
