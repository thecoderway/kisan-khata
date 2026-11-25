<?php

namespace App\Modules\Users\Application;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserDirectoryQuery
{
    /**
     * Managers receive only Staff identity and status fields.
     * Owners receive permitted shop-user account details.
     */
    public function forActor(User $actor, Request $request): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', User::class);

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $role = (string) $request->query('role', 'all');

        $query = User::query()->where('shop_id', $actor->shop_id);

        if ($actor->role === User::ROLE_MANAGER) {
            $query->where('role', User::ROLE_STAFF);
        } else {
            if (in_array($role, [User::ROLE_MANAGER, User::ROLE_STAFF], true)) {
                $query->where('role', $role);
            } else {
                $role = 'all';
            }
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $query->where('is_active', $status === 'active');
        } else {
            $status = 'all';
        }

        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $pageSize = filter_var($request->query('pageSize', 20), FILTER_VALIDATE_INT);
        $pageSize = in_array($pageSize, [10, 20, 50, 100], true) ? $pageSize : 20;

        $columns = $actor->role === User::ROLE_MANAGER
            ? ['id', 'name', 'role', 'is_active']
            : ['id', 'name', 'email', 'mobile', 'role', 'is_active', 'created_at'];

        return $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($pageSize, $columns)
            ->withQueryString();
    }
}
