<?php

namespace App\Modules\Customers\Application;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class CustomerDirectoryQuery
{
    public function paginate(Request $request): LengthAwarePaginator
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', Customer::STATUS_ACTIVE);

        $query = in_array($status, ['deleted', 'financial-history'], true)
            ? Customer::withTrashed()
            : Customer::query();

        $query->leftJoin('villages', 'customers.village_code', '=', 'villages.villageCode')
            ->select([
                'customers.*',
                'villages.villageNameLocal as village_name_local',
                'villages.villageNameEnglish as village_name_english',
            ]);

        if ($status === 'deleted') {
            $query->whereNotNull('customers.deleted_at');
        } elseif ($status === 'financial-history') {
            $query->where(function ($history): void {
                foreach (['sales', 'payments', 'ledger_entries', 'financial_transactions', 'customer_accounts', 'account_balance_snapshots', 'payment_allocations', 'payment_allocation_items', 'customer_interest_policy_versions', 'interest_calculation_runs', 'interest_calculation_items', 'interest_lots', 'interest_lot_events', 'interest_settlement_runs', 'interest_settlement_items'] as $table) {
                    $history->orWhereExists(fn ($subquery) => $subquery
                        ->selectRaw('1')
                        ->from($table)
                        ->whereColumn($table.'.customer_id', 'customers.id'));
                }
            });
        } elseif ($status === Customer::STATUS_INACTIVE) {
            $query->where('customers.status', Customer::STATUS_INACTIVE);
        } elseif ($status === 'all') {
            // All non-deleted customers; used only for Owner-managed policy workflows.
        } else {
            $query->where('customers.status', Customer::STATUS_ACTIVE);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $term = '%'.$search.'%';
                $builder->where('customers.name_original', 'like', $term)
                    ->orWhere('customers.name_hi', 'like', $term)
                    ->orWhere('customers.name_en', 'like', $term)
                    ->orWhere('customers.mobile', 'like', $term);
            });
        }

        $requestedPageSize = filter_var($request->query('pageSize', 20), FILTER_VALIDATE_INT);
        $pageSize = in_array($requestedPageSize, [10, 20, 50, 100], true)
            ? $requestedPageSize
            : 20;

        return $query
            ->orderBy('customers.name_original')
            ->orderBy('customers.id')
            ->paginate($pageSize)
            ->withQueryString();
    }
}
