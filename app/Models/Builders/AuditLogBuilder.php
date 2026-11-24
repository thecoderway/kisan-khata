<?php

namespace App\Models\Builders;

use App\Tenancy\CurrentShop;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Enforces tenant ownership and append-only semantics for generic audit history.
 *
 * @template TModel of \App\Models\AuditLog
 *
 * @extends Builder<TModel>
 */
class AuditLogBuilder extends Builder
{
    public function update(array $values) { throw new LogicException('Audit records are append-only.'); }
    public function delete() { throw new LogicException('Audit records are append-only.'); }
    public function truncate() { throw new LogicException('Audit records are append-only.'); }
    public function increment($column, $amount = 1, array $extra = []) { throw new LogicException('Audit records are append-only.'); }
    public function decrement($column, $amount = 1, array $extra = []) { throw new LogicException('Audit records are append-only.'); }
    public function incrementEach(array $columns, array $extra = []) { throw new LogicException('Audit records are append-only.'); }
    public function decrementEach(array $columns, array $extra = []) { throw new LogicException('Audit records are append-only.'); }
    public function touch($column = null) { throw new LogicException('Audit records are append-only.'); }
    public function upsert(array $values, $uniqueBy, $update = null) { throw new LogicException('Audit records are append-only.'); }
    public function updateOrInsert(array $attributes, array|callable $values = []): bool { throw new LogicException('Audit records are append-only.'); }

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

    public function insertUsing(array $columns, $query) { throw new LogicException('Audit records require explicit tenant-scoped insertion.'); }
    public function insertOrIgnoreUsing(array $columns, $query) { throw new LogicException('Audit records require explicit tenant-scoped insertion.'); }
    public function insertOrIgnoreReturning(array $values, array $returning = ['*'], array|string|null $uniqueBy = null) { throw new LogicException('Audit records require explicit tenant-scoped insertion.'); }

    private function withCurrentShop(array $values): array
    {
        $shopId = app(CurrentShop::class)->id();
        if ($shopId === null) throw new LogicException('A current shop context is required for tenant audit records.');
        if ($values === []) return $values;

        $isBatch = array_is_list($values) && isset($values[0]) && is_array($values[0]);
        $rows = $isBatch ? $values : [$values];
        foreach ($rows as &$row) {
            if (array_key_exists('shop_id', $row) && (int) $row['shop_id'] !== $shopId) {
                throw new AuthorizationException('An audit record cannot be created for another shop.');
            }
            $row['shop_id'] = $shopId;
        }
        unset($row);

        return $isBatch ? $rows : $rows[0];
    }
}
