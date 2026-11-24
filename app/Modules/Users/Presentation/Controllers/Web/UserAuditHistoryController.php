<?php

namespace App\Modules\Users\Presentation\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\UserAuditLog;
use App\Tenancy\CurrentShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserAuditHistoryController extends Controller
{
    public function __invoke(Request $request, CurrentShop $currentShop): View
    {
        Gate::authorize('viewAny', UserAuditLog::class);

        $data = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $logs = UserAuditLog::query()
            ->where('shop_id', $currentShop->require()->getKey())
            ->with([
                'actor:id,name',
                'target:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 25)
            ->withQueryString();

        return view('users.audit', ['logs' => $logs]);
    }
}
