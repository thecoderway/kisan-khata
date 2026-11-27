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
        $customer = Customer::withTrashed()->findOrFail($customer);
        abort_unless(! $customer->trashed() || $customer->hasFinancialHistory(), 404);
        Gate::authorize('viewCustomerReconciliation', $customer);

        return response()->json([
            'data' => $reconciliation->reconcileCustomer($customer),
        ]);
    }
}
