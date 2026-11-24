<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Users\Application\ManageShopUserLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShopUserLifecycleController extends Controller
{
    public function deactivate(Request $request, int $user, ManageShopUserLifecycle $lifecycle): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $target = $lifecycle->deactivate($request->user(), $user, $data['reason'] ?? null);

        return response()->json(['user' => $this->userPayload($target)]);
    }

    public function reactivate(Request $request, int $user, ManageShopUserLifecycle $lifecycle): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $target = $lifecycle->reactivate($request->user(), $user, $data['reason'] ?? null);

        return response()->json(['user' => $this->userPayload($target)]);
    }

    public function changeRole(Request $request, int $user, ManageShopUserLifecycle $lifecycle): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', 'string', Rule::in(['manager', 'staff'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $target = $lifecycle->changeRole($request->user(), $user, $data['role'], $data['reason'] ?? null);

        return response()->json(['user' => $this->userPayload($target)]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role,
            'is_active' => $user->is_active,
        ];
    }
}
