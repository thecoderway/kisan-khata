<?php

namespace App\Modules\Financial\Presentation\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Modules\Financial\Application\ReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CustomerReconciliationController extends Controller
{
    public function show(int $customer, ReconciliationService $reconciliation): JsonResponse
    {
        $customer = Customer::query()->findOrFail($customer);
        Gate::authorize('viewCustomerReconciliation', $customer);

        return response()->json([
            'data' => $reconciliation->reconcileCustomer($customer),
        ]);
    }

    public function repair(int $customer, ReconciliationService $reconciliation): JsonResponse
    {
        $customer = Customer::query()->findOrFail($customer);
        Gate::authorize('repairCustomerProjection', $customer);

        request()->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        return response()->json([
            'message' => 'Projection repair completed.',
            'data' => $reconciliation->rebuildAndReconcile($customer),
        ]);
    }
}
