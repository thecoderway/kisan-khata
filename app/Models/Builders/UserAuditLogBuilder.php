<?php

namespace App\Models\Builders;

use App\Tenancy\CurrentShop;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Enforce tenant ownership and append-only behavior for user audit history.
 *
 * @template TModel of \App\Models\UserAuditLog
 *
 * @extends Builder<TModel>
 */
class UserAuditLogBuilder extends Builder
{
    public function update(array $values)
    {
        throw new LogicException('User audit records are append-only.');
    }

    public function delete()
    {
        throw new LogicException('User audit records are append-only.');
    }

    public function truncate()
    {
        throw new LogicException('User audit records are append-only.');
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        throw new LogicException('User audit records are append-only.');
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        throw new LogicException('User audit records are append-only.');
    }

    public function incrementEach(array $columns, array $extra = [])
    {
        throw new LogicException('User audit records are append-only.');
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        throw new LogicException('User audit records are append-only.');
    }

    public function touch($column = null)
    {
        throw new LogicException('User audit records are append-only.');
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw new LogicException('User audit records are append-only.');
    }

    public function updateOrInsert(array $attributes, array|callable $values = []): bool
    {
        throw new LogicException('User audit records are append-only.');
    }

    public function insert(array $values)
    {
        return $this->toBase()->insert($this->withCurrentShop($values));
    }

    public function insertOrIgnore(array $values)
    {
        return $this->toBase()->insertOrIgnore($this->withCurrentShop($values));
    }

    public function insertGetId(array $values, $sequence = null)
    {
        return $this->toBase()->insertGetId($this->withCurrentShop($values), $sequence);
    }

    public function insertUsing(array $columns, $query)
    {
        throw new LogicException('User audit records must be inserted with an explicit current shop context.');
    }

    public function insertOrIgnoreUsing(array $columns, $query)
    {
        throw new LogicException('User audit records must be inserted with an explicit current shop context.');
    }

    public function insertOrIgnoreReturning(array $values, array $returning = ['*'], array|string|null $uniqueBy = null)
    {
        throw new LogicException('User audit records must be inserted through tenant-scoped insert operations.');
    }

    private function withCurrentShop(array $values): array
    {
        $shopId = app(CurrentShop::class)->id();

        if ($shopId === null) {
            throw new LogicException('A current shop context is required for user audit records.');
        }

        if ($values === []) {
            return $values;
        }

        $isBatch = array_is_list($values) && isset($values[0]) && is_array($values[0]);
        $rows = $isBatch ? $values : [$values];

        foreach ($rows as &$row) {
            if (array_key_exists('shop_id', $row) && (int) $row['shop_id'] !== $shopId) {
                throw new AuthorizationException('An audit record cannot be inserted for another shop.');
            }

            $row['shop_id'] = $shopId;
        }
        unset($row);

        return $isBatch ? $rows : $rows[0];
    }
}
