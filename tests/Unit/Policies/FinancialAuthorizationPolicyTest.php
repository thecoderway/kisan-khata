<?php

namespace Tests\Unit\Policies;

use App\Models\Customer;
use App\Models\InterestPolicy;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Policies\CustomerLedgerPolicy;
use App\Policies\FinancialAuditPolicy;
use App\Policies\InterestPolicyPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ReconciliationPolicy;
use App\Policies\SalePolicy;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinancialAuthorizationPolicyTest extends TestCase
{
    #[Test]
    public function financial_policies_are_registered_with_gate(): void
    {
        $this->assertInstanceOf(SalePolicy::class, Gate::getPolicyFor(Sale::class));
        $this->assertInstanceOf(PaymentPolicy::class, Gate::getPolicyFor(Payment::class));
        $this->assertInstanceOf(InterestPolicyPolicy::class, Gate::getPolicyFor(InterestPolicy::class));
        $this->assertTrue(Gate::has('viewCustomerLedger'));
        $this->assertTrue(Gate::has('viewFinancialAudit'));
        $this->assertTrue(Gate::has('viewReconciliation'));
        $this->assertTrue(Gate::has('viewCustomerReconciliation'));
        $this->assertTrue(Gate::has('repairCustomerProjection'));
    }

    #[Test]
    public function all_active_shop_roles_can_view_all_shop_customer_ledgers(): void
    {
        $policy = new CustomerLedgerPolicy;
        $customer = $this->customer(10);

        foreach ([User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_STAFF] as $role) {
            $this->assertTrue($policy->view($this->user($role), $customer), $role.' should view every Customer ledger in their Shop');
        }
    }

    #[Test]
    public function customer_ledger_access_rejects_inactive_and_cross_shop_users(): void
    {
        $policy = new CustomerLedgerPolicy;
        $customer = $this->customer(10);

        $this->assertFalse($policy->view($this->user(User::ROLE_STAFF, 10, false), $customer));
        $this->assertFalse($policy->view($this->user(User::ROLE_STAFF, 20), $customer));
        $this->assertFalse($policy->view($this->user(User::ROLE_PLATFORM_OWNER, null), $customer));
    }

    #[Test]
    public function staff_and_manager_can_view_shop_sales_and_payments_but_only_owner_can_correct(): void
    {
        $sale = new Sale;
        $sale->forceFill(['id' => 101, 'shop_id' => 10]);
        $payment = new Payment;
        $payment->forceFill(['id' => 201, 'shop_id' => 10]);

        $sales = new SalePolicy;
        $payments = new PaymentPolicy;

        foreach ([User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_STAFF] as $role) {
            $actor = $this->user($role);
            $this->assertTrue($sales->viewAny($actor));
            $this->assertTrue($sales->view($actor, $sale));
            $this->assertTrue($sales->create($actor));
            $this->assertTrue($payments->viewAny($actor));
            $this->assertTrue($payments->view($actor, $payment));
            $this->assertTrue($payments->create($actor));
        }

        $this->assertTrue($sales->revise($this->user(User::ROLE_OWNER), $sale));
        $this->assertFalse($sales->revise($this->user(User::ROLE_MANAGER), $sale));
        $this->assertFalse($sales->revise($this->user(User::ROLE_STAFF), $sale));
        $this->assertTrue($payments->correct($this->user(User::ROLE_OWNER), $payment));
        $this->assertFalse($payments->correct($this->user(User::ROLE_MANAGER), $payment));
        $this->assertFalse($payments->correct($this->user(User::ROLE_STAFF), $payment));
    }

    #[Test]
    public function only_owner_can_change_interest_settings_or_repair_reconciliation(): void
    {
        $customer = $this->customer(10);
        $interest = new InterestPolicyPolicy;
        $reconciliation = new ReconciliationPolicy;

        $this->assertTrue($interest->update($this->user(User::ROLE_OWNER)));
        $this->assertFalse($interest->update($this->user(User::ROLE_MANAGER)));
        $this->assertFalse($interest->update($this->user(User::ROLE_STAFF)));

        $this->assertTrue($interest->updateCustomer($this->user(User::ROLE_OWNER), $customer));
        $this->assertFalse($interest->updateCustomer($this->user(User::ROLE_STAFF), $customer));

        $this->assertTrue($reconciliation->viewCustomer($this->user(User::ROLE_OWNER), $customer));
        $this->assertFalse($reconciliation->viewCustomer($this->user(User::ROLE_MANAGER), $customer));
        $this->assertFalse($reconciliation->repair($this->user(User::ROLE_STAFF), $customer));
        $this->assertFalse((new FinancialAuditPolicy)->viewAny($this->user(User::ROLE_MANAGER)));
    }

    #[Test]
    public function every_financial_policy_rejects_cross_shop_records(): void
    {
        $staff = $this->user(User::ROLE_STAFF, 10);
        $foreignSale = new Sale;
        $foreignSale->forceFill(['shop_id' => 20]);
        $foreignPayment = new Payment;
        $foreignPayment->forceFill(['shop_id' => 20]);
        $foreignCustomer = $this->customer(20);

        $this->assertFalse((new SalePolicy)->view($staff, $foreignSale));
        $this->assertFalse((new PaymentPolicy)->view($staff, $foreignPayment));
        $this->assertFalse((new CustomerLedgerPolicy)->view($staff, $foreignCustomer));
        $this->assertFalse((new InterestPolicyPolicy)->viewCustomer($staff, $foreignCustomer));
    }

    private function user(string $role, ?int $shopId = 10, bool $active = true): User
    {
        static $nextId = 1;

        $user = new User;
        $user->forceFill([
            'id' => $nextId++,
            'shop_id' => $shopId,
            'role' => $role,
            'is_active' => $active,
        ]);

        return $user;
    }

    private function customer(int $shopId): Customer
    {
        static $nextId = 1000;

        $customer = new Customer;
        $customer->forceFill(['id' => $nextId++, 'shop_id' => $shopId]);

        return $customer;
    }
}
