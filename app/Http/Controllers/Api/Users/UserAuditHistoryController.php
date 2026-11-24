<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Controller;
use App\Models\UserAuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserAuditHistoryController extends Controller
{
    public function __invoke(Request $request): LengthAwarePaginator
    {
        Gate::authorize('viewAny', UserAuditLog::class);

        $data = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return UserAuditLog::query()
            ->where('shop_id', $request->user()->shop_id)
            ->with([
                'actor:id,name',
                'target:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 25);
    }
}
