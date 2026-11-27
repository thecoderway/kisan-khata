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

        $query = in_array($status, ['deleted', 'financial-history'], true)\n            ? Customer::withTrashed()\n            : Customer::query();

        $query->leftJoin('villages', 'customers.village_code', '=', 'villages.villageCode')
            ->select([
                'customers.*',
                'villages.villageNameLocal as village_name_local',
                'villages.villageNameEnglish as village_name_english',
            ]);

        if ($status === 'deleted') {
            $query->whereNotNull('customers.deleted_at');
        } elseif ($status === Customer::STATUS_INACTIVE) {
            $query->where('customers.status', Customer::STATUS_INACTIVE);
        } else {
            $status = Customer::STATUS_ACTIVE;
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
