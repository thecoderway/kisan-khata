<?php

namespace App\\Modules\\Customers\\Application;

use App\\Models\\Customer;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;

class CustomerDeletionEligibility
{
    /**
     * Every table containing customer financial history must be checked.
     *
     * Missing tables or columns are unknown dependencies, not proof that the
     * customer has no financial history. Keep this registry aligned with the
     * financial schema and fail closed when it cannot be verified.
     *
     * @var array<string, string>
     */
    private const FINANCIAL_DEPENDENCIES = [
        'customer_accounts' => 'customer_id',
        'financial_transactions' => 'customer_id',
        'sales' => 'customer_id',
        'payments' => 'customer_id',
        'payment_revisions' => 'customer_id',
        'payment_allocations' => 'customer_id',
        'payment_allocation_items' => 'customer_id',
        'ledger_entries' => 'customer_id',
        'account_balance_snapshots' => 'customer_id',
        'customer_interest_policy_versions' => 'customer_id',
        'interest_calculation_runs' => 'customer_id',
        'interest_calculation_items' => 'customer_id',
        'interest_lots' => 'customer_id',
        'interest_lot_events' => 'customer_id',
        'interest_settlement_runs' => 'customer_id',
        'interest_settlement_items' => 'customer_id',
    ];

    public function check(Customer $customer): array
    {
        foreach (self::FINANCIAL_DEPENDENCIES as $table => $foreignKey) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $foreignKey)) {
                return [
                    'eligible' => false,
                    'reason' => 'Customer deletion eligibility cannot be verified until all financial history tables are available.',
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
