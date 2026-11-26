<?php

namespace App\Providers;

use App\Contracts\MobileOtpSender;
use App\Infrastructure\Auth\NullMobileOtpSender;
use App\Models\Customer;
use App\Models\InterestPolicy;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Models\UserAuditLog;
use App\Policies\CustomerLedgerPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\FinancialAuditPolicy;
use App\Policies\InterestPolicyPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ReconciliationPolicy;
use App\Policies\SalePolicy;
use App\Policies\UserAuditLogPolicy;
use App\Policies\UserManagementPolicy;
use App\Tenancy\CurrentShop;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentShop::class, fn (): CurrentShop => new CurrentShop);

        $this->app->bind(MobileOtpSender::class, function (): MobileOtpSender {
            $sender = config('mobile_otp.sender');

            if (is_string($sender) && class_exists($sender) && is_subclass_of($sender, MobileOtpSender::class)) {
                return app($sender);
            }

            return new NullMobileOtpSender;
        });
    }

    public function boot(): void
    {
        Gate::policy(User::class, UserManagementPolicy::class);
        Gate::policy(UserAuditLog::class, UserAuditLogPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Sale::class, SalePolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(InterestPolicy::class, InterestPolicyPolicy::class);

        Gate::define('viewCustomerLedger', fn (User $actor, Customer $customer): bool => app(CustomerLedgerPolicy::class)->view($actor, $customer));

        Gate::define('viewFinancialAudit', fn (User $actor): bool => app(FinancialAuditPolicy::class)->viewAny($actor));
        Gate::define('viewReconciliation', fn (User $actor): bool => app(ReconciliationPolicy::class)->viewAny($actor));
        Gate::define('viewCustomerReconciliation', fn (User $actor, Customer $customer): bool => app(ReconciliationPolicy::class)->viewCustomer($actor, $customer));
        Gate::define('repairCustomerProjection', fn (User $actor, Customer $customer): bool => app(ReconciliationPolicy::class)->repair($actor, $customer));
    }
}
