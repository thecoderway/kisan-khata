<?php

namespace App\Models\Builders;

use App\Tenancy\CurrentShop;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Enforces tenant ownership for set-based Eloquent mutations, which do not
 * dispatch per-model updating/deleting events.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends Builder<TModel>
 */
class TenantOwnedBuilder extends Builder
{
    public function update(array $values)
    {
        $shopId = $this->requireCurrentShopId();
        $this->assertOwnershipIsImmutable(array_keys($values));

        return $this->where(
            $this->getModel()->qualifyColumn('shop_id'),
            $shopId,
        )->toBase()->update($values);
    }

    public function delete($id = null)
    {
        if ($id !== null) {
            $this->whereKey($id);
        }

        return $this->where(
            $this->getModel()->qualifyColumn('shop_id'),
            $this->requireCurrentShopId(),
        )->toBase()->delete();
    }

    public function touch($column = null)
    {
        $this->requireCurrentShopId();

        $column ??= $this->getModel()->getUpdatedAtColumn();

        if (! $this->getModel()->usesTimestamps() || $column === null) {
            return false;
        }

        $columns = is_array($column) ? $column : [$column];
        $this->assertOwnershipIsImmutable($columns);

        $timestamp = $this->getModel()->freshTimestamp();
        $values = array_fill_keys($columns, $timestamp);

        return $this->update($values);
    }

    /**
     * Upsert only within the current tenant and make tenant ownership immutable.
     *
     * @param  array<int, array<string, mixed>>|array<string, mixed>  $values
     * @param  array<int, string>|string  $uniqueBy
     * @param  array<int, string>|null  $update
     */
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        $shopId = $this->requireCurrentShopId();

        if ($values === []) {
            return 0;
        }

        $isBatch = array_is_list($values) && isset($values[0]) && is_array($values[0]);
        $rows = $isBatch ? $values : [$values];

        foreach ($rows as &$row) {
            if (! is_array($row)) {
                throw new LogicException('Tenant-owned upsert values must be row arrays.');
            }

            if (array_key_exists('shop_id', $row) && (int) $row['shop_id'] !== $shopId) {
                throw new AuthorizationException('A record cannot be upserted for another shop.');
            }

            $row['shop_id'] = $shopId;
        }
        unset($row);

        $uniqueColumns = is_array($uniqueBy) ? $uniqueBy : [$uniqueBy];

        if (! in_array('shop_id', $uniqueColumns, true)) {
            $uniqueColumns[] = 'shop_id';
        }

        if ($update !== null) {
            $this->assertOwnershipIsImmutable($update);
        } else {
            $update = array_values(array_diff(array_keys($rows[0]), ['shop_id']));
        }

        return parent::upsert($rows, $uniqueColumns, $update);
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->assertOwnershipIsImmutable([$column, ...array_keys($extra)]);

        return $this->where(
            $this->getModel()->qualifyColumn('shop_id'),
            $this->requireCurrentShopId(),
        )->toBase()->increment($column, $amount, $extra);
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->assertOwnershipIsImmutable([$column, ...array_keys($extra)]);

        return $this->where(
            $this->getModel()->qualifyColumn('shop_id'),
            $this->requireCurrentShopId(),
        )->toBase()->decrement($column, $amount, $extra);
    }

    public function incrementEach(array $columns, array $extra = [])
    {
        $this->assertOwnershipIsImmutable([...array_keys($columns), ...array_keys($extra)]);

        return $this->where(
            $this->getModel()->qualifyColumn('shop_id'),
            $this->requireCurrentShopId(),
        )->toBase()->incrementEach($columns, $this->addUpdatedAtColumn($extra));
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        $this->assertOwnershipIsImmutable([...array_keys($columns), ...array_keys($extra)]);

        return $this->where(
            $this->getModel()->qualifyColumn('shop_id'),
            $this->requireCurrentShopId(),
        )->toBase()->decrementEach($columns, $this->addUpdatedAtColumn($extra));
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
        throw new LogicException('Tenant-owned insertUsing is not supported; use an explicitly tenant-scoped service operation.');
    }

    public function insertOrIgnoreUsing(array $columns, $query)
    {
        throw new LogicException('Tenant-owned insertOrIgnoreUsing is not supported; use an explicitly tenant-scoped service operation.');
    }

    public function insertOrIgnoreReturning(array $values, array $returning = ['*'], array|string|null $uniqueBy = null)
    {
        throw new LogicException('Tenant-owned insertOrIgnoreReturning is not supported; use an explicitly tenant-scoped service operation.');
    }

    public function updateOrInsert(array $attributes, array|callable $values = [])
    {
        throw new LogicException('Tenant-owned updateOrInsert is not supported; use upsert or model save operations.');
    }

    public function truncate()
    {
        throw new LogicException('Tenant-owned tables cannot be truncated through a model query.');
    }

    /**
     * @param  array<int, array<string, mixed>>|array<string, mixed>  $values
     * @return array<int, array<string, mixed>>|array<string, mixed>
     */
    private function withCurrentShop(array $values): array
    {
        $shopId = $this->requireCurrentShopId();

        if ($values === []) {
            return $values;
        }

        $rows = array_is_list($values) && isset($values[0]) && is_array($values[0])
            ? $values
            : [$values];

        foreach ($rows as &$row) {
            if (! is_array($row)) {
                throw new LogicException('Tenant-owned insert values must be row arrays.');
            }

            if (array_key_exists('shop_id', $row) && (int) $row['shop_id'] !== $shopId) {
                throw new AuthorizationException('A record cannot be inserted for another shop.');
            }

            $row['shop_id'] = $shopId;
        }
        unset($row);

        return $isBatch ? $rows : $rows[0];
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function assertOwnershipIsImmutable(array $columns): void
    {
        foreach ($columns as $column) {
            if (! is_string($column)) {
                continue;
            }

            $normalized = str_replace('`', '', $column);
            $lastSegment = substr($normalized, strrpos('.'.$normalized, '.') + 1);

            if ($lastSegment === 'shop_id') {
                throw new AuthorizationException('Tenant ownership cannot be changed through a bulk mutation.');
            }
        }
    }

    private function requireCurrentShopId(): int
    {
        $shopId = app(CurrentShop::class)->id();

        if ($shopId === null) {
            throw new LogicException('A current shop context is required for tenant-owned bulk mutations.');
        }

        return $shopId;
    }
}
