<?php

namespace App\Modules\Customers\Application;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerDeletionEligibility
{
    /**
     * Every financial domain must be verifiable before deletion is allowed.
     *
     * Keep this registry aligned with future Sales, Payments, Udhaar and Ledger
     * modules. A missing table/column is an unknown dependency, not proof that
     * the customer has no financial history.
     *
     * @var array<string, string>
     */
    private const FINANCIAL_DEPENDENCIES = [
        'sales' => 'customer_id',
        'payments' => 'customer_id',
        'udhaar_entries' => 'customer_id',
        'ledger_entries' => 'customer_id',
    ];

    public function check(Customer $customer): array
    {
        foreach (self::FINANCIAL_DEPENDENCIES as $table => $foreignKey) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $foreignKey)) {
                return [
                    'eligible' => false,
                    'reason' => 'Customer deletion eligibility cannot be verified until all financial history modules are available.',
                ];
            }

            if (DB::table($table)
                ->where($foreignKey, $customer->getKey())
                ->lockForUpdate()
                ->exists()) {
                return [
                    'eligible' => false,
                    'reason' => 'Customer has associated financial or transaction history.',
                ];
            }
        }

        return ['eligible' => true, 'reason' => null];
    }
}
