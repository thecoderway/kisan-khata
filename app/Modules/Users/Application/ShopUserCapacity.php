<?php

namespace App\Modules\Users\Application;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ShopUserCapacity
{
    private const ROLE_LIMITS = [
        User::ROLE_OWNER => 1,
        User::ROLE_MANAGER => 2,
        User::ROLE_STAFF => 5,
    ];

    private const TOTAL_ACTIVE_USER_LIMIT = 8;

    /**
     * Lock the shop row. Every operation that changes active membership or a
     * shop user's role must call this inside its transaction before counting.
     */
    public function lockActiveShop(?int $shopId): Shop
    {
        $shop = $shopId === null
            ? null
            : Shop::query()->whereKey($shopId)->lockForUpdate()->first();

        if (! $shop || $shop->status !== Shop::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'shop' => 'Users can only be managed in an active shop.',
            ]);
        }

        return $shop;
    }

    /**
     * Return current active-user usage and configured limits for a shop.
     *
     * @return array{
     *     roles: array<string, array{active: int, limit: int}>,
     *     active_total: int,
     *     total_limit: int
     * }
     */
    public function summary(Shop $shop): array
    {
        $counts = $this->activeRoleCounts($shop);

        $roles = [];

        foreach (self::ROLE_LIMITS as $role => $limit) {
            $roles[$role] = [
                'active' => $counts[$role] ?? 0,
                'limit' => $limit,
            ];
        }

        return [
            'roles' => $roles,
            'active_total' => array_sum($counts),
            'total_limit' => self::TOTAL_ACTIVE_USER_LIMIT,
        ];
    }

    /**
     * Assert that adding one active account preserves every role limit and
     * the shop-wide active-user limit. Caller must already hold the shop lock.
     */
    public function assertCanAdd(Shop $shop, string $role): void
    {
        $this->assertSupportedRole($role);

        $counts = $this->activeRoleCounts($shop);
        $counts[$role] = ($counts[$role] ?? 0) + 1;

        $this->assertWithinLimits($counts);
    }

    /**
     * Shared invariant check for activation/deactivation and role transitions.
     * Caller must hold the same shop row lock in the surrounding transaction.
     */
    public function assertCanTransition(
        Shop $shop,
        User $target,
        bool $nextActive,
        string $nextRole,
    ): array {
        if ((string) $target->shop_id !== (string) $shop->getKey()) {
            throw ValidationException::withMessages([
                'user' => 'The user does not belong to this shop.',
            ]);
        }

        $this->assertSupportedRole($nextRole);

        $counts = $this->activeRoleCounts($shop);

        if ($target->is_active) {
            $counts[$target->role] = max(0, ($counts[$target->role] ?? 0) - 1);
        }

        if ($nextActive) {
            $counts[$nextRole] = ($counts[$nextRole] ?? 0) + 1;
        }

        $this->assertWithinLimits($counts);

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private function activeRoleCounts(Shop $shop): array
    {
        return $shop->users()
            ->where('is_active', true)
            ->selectRaw('role, COUNT(*) as aggregate')
            ->groupBy('role')
            ->pluck('aggregate', 'role')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    private function assertSupportedRole(string $role): void
    {
        if (! array_key_exists($role, self::ROLE_LIMITS)) {
            throw ValidationException::withMessages([
                'role' => 'This role is not a supported shop role.',
            ]);
        }
    }

    /**
     * @param array<string, int> $counts
     */
    private function assertWithinLimits(array $counts): void
    {
        if (array_sum($counts) > self::TOTAL_ACTIVE_USER_LIMIT) {
            throw ValidationException::withMessages([
                'role' => 'This shop has reached its maximum of 8 active users.',
            ]);
        }

        foreach (self::ROLE_LIMITS as $role => $limit) {
            if (($counts[$role] ?? 0) > $limit) {
                throw ValidationException::withMessages([
                    'role' => "This shop has reached its maximum of {$limit} active {$role} users.",
                ]);
            }
        }
    }
}
