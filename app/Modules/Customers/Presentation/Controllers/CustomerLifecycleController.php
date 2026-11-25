<?php

namespace App\Modules\Customers\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Modules\Customers\Application\ManageCustomerLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerLifecycleController extends Controller
{
    public function deactivate(Request $request, int $customer, ManageCustomerLifecycle $lifecycle): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:1', 'max:500']]);
        $result = $lifecycle->deactivate($request->user(), $customer, $data['reason'], $request->ip(), $request->userAgent());

        return $this->respond($request, $result, 'ग्राहक निष्क्रिय कर दिया गया है।');
    }

    public function reactivate(Request $request, int $customer, ManageCustomerLifecycle $lifecycle): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $result = $lifecycle->reactivate($request->user(), $customer, $data['reason'] ?? null, $request->ip(), $request->userAgent());

        return $this->respond($request, $result, 'ग्राहक पुनः सक्रिय कर दिया गया है।');
    }

    public function softDelete(Request $request, int $customer, ManageCustomerLifecycle $lifecycle): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:1', 'max:500']]);
        $result = $lifecycle->softDelete($request->user(), $customer, $data['reason'], $request->ip(), $request->userAgent());

        return $this->respond($request, $result, 'ग्राहक को soft delete कर दिया गया है।');
    }

    public function restore(Request $request, int $customer, ManageCustomerLifecycle $lifecycle): JsonResponse|RedirectResponse
    {
        $result = $lifecycle->restore($request->user(), $customer, $request->ip(), $request->userAgent());

        return $this->respond($request, $result, 'ग्राहक restore कर दिया गया है।');
    }

    private function respond(Request $request, Customer $customer, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'data' => [
                    'id' => $customer->getKey(),
                    'status' => $customer->status,
                    'deleted_at' => $customer->deleted_at?->toISOString(),
                ],
            ]);
        }

        $redirect = $request->boolean('drawer_context')
            ? redirect()->route('customers.index')
            : back();

        return $redirect->with('status', $message);
    }
}
