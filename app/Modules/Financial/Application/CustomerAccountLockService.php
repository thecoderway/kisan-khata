<?php

namespace App\Modules\Financial\Application;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Tenancy\CurrentShop;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

class CustomerAccountLockService
{
    public function __construct(private readonly CurrentShop $currentShop) {}

    /**
     * Lock the customer row and its account row in a consistent order.
     *
     * The customer lock also serializes against the customer lifecycle service,
     * preventing a financial posting from racing a customer soft-delete check.
     */
    public function lockForCustomer(Customer|int $customer): CustomerAccount
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('A customer account lock requires an active database transaction.');
        }

        $shopId = (int) $this->currentShop->require()->getKey();
        $customerId = $customer instanceof Customer ? (int) $customer->getKey() : $customer;

        if ($customer instanceof Customer && (int) $customer->shop_id !== $shopId) {
            throw new AuthorizationException('The customer does not belong to the current shop.');
        }

        $lockedCustomer = Customer::query()
            ->whereKey($customerId)
            ->lockForUpdate()
            ->firstOrFail();

        if ((int) $lockedCustomer->shop_id !== $shopId) {
            throw new AuthorizationException('The customer does not belong to the current shop.');
        }

        $account = CustomerAccount::query()
            ->where('customer_id', $customerId)
            ->lockForUpdate()
            ->first();

        if ($account === null) {
            $account = CustomerAccount::query()->create([
                'customer_id' => $customerId,
            ]);

            // Hydrate database defaults (balances and projection version) before
            // any posting service applies arithmetic to this new account.
            $account->refresh();
        }

        if ((int) $account->shop_id !== $shopId || (int) $account->customer_id !== $customerId) {
            throw new AuthorizationException('The customer account does not belong to the current shop.');
        }

        return $account;
    }
}
