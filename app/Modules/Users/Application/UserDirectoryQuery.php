<?php

namespace App\Modules\Users\Application;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

class UserDirectoryQuery
{
    /**
     * Return the directory projection permitted for the authenticated actor.
     *
     * Managers receive only Staff identity and status fields.
     * Owners receive the permitted shop-user account details.
     *
     * @return Collection<int, User>
     */
    public function forActor(User $actor): Collection
    {
        Gate::forUser($actor)->authorize('viewAny', User::class);

        $query = User::query()
            ->where('shop_id', $actor->shop_id);

        if ($actor->role === User::ROLE_MANAGER) {
            return $query
                ->where('role', User::ROLE_STAFF)
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'role',
                    'is_active',
                ]);
        }

        return $query
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'mobile',
                'role',
                'is_active',
                'created_at',
            ]);
    }
}
