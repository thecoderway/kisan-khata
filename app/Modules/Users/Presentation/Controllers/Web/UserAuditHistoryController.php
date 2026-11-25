<?php

namespace App\Modules\Users\Presentation\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\UserAuditLog;
use App\Tenancy\CurrentShop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserAuditHistoryController extends Controller
{
    public function __invoke(Request $request, CurrentShop $currentShop): View|JsonResponse
    {
        Gate::authorize('viewAny', UserAuditLog::class);

        $data = $request->validate([
            'q' => ['sometimes', 'string', 'max:100'],
            'action' => ['sometimes', 'string', 'in:all,user.created,user.deactivated,user.reactivated,user.role_changed'],
            'pageSize' => ['sometimes', 'integer', 'in:10,20,50,100'],
        ]);

        $query = UserAuditLog::query()
            ->where('shop_id', $currentShop->require()->getKey())
            ->with(['actor:id,name', 'target:id,name']);

        if (! empty($data['action']) && $data['action'] !== 'all') {
            $query->where('action', $data['action']);
        }

        $search = trim($data['q'] ?? '');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('reason', 'like', '%'.$search.'%')
                    ->orWhereHas('actor', fn ($actor) => $actor->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('target', fn ($target) => $target->where('name', 'like', '%'.$search.'%'));
            });
        }

        $logs = $query->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($data['pageSize'] ?? 20)
            ->withQueryString();

        if ($request->expectsJson() && $request->boolean('fragment')) {
            return response()->json([
                'html' => view('users.partials.audit-results', compact('logs'))->render(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                ],
            ]);
        }

        return view('users.audit', ['logs' => $logs]);
    }
}
