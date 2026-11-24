<?php

namespace App\Models\Builders;

use App\Tenancy\CurrentShop;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Prevent bulk Eloquent mutations from changing a user's tenant association
 * or deleting retained user accounts.
 *
 * @template TModel of \App\Models\User
 *
 * @extends Builder<TModel>
 */
class UserBuilder extends Builder
{
    public function update(array $values)
    {
        $this->assertShopIdIsImmutable(array_keys($values));
        $this->scopeToCurrentShop();

        return parent::update($values);
    }

    public function delete()
    {
        throw new LogicException('Shop user accounts are retained; use the audited deactivation lifecycle.');
    }

    public function truncate()
    {
        throw new LogicException('The users table cannot be truncated through Eloquent.');
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->assertShopIdIsImmutable([$column, ...array_keys($extra)]);
        $this->scopeToCurrentShop();

        return parent::increment($column, $amount, $extra);
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->assertShopIdIsImmutable([$column, ...array_keys($extra)]);
        $this->scopeToCurrentShop();

        return parent::decrement($column, $amount, $extra);
    }

    public function incrementEach(array $columns, array $extra = [])
    {
        $this->assertShopIdIsImmutable([...array_keys($columns), ...array_keys($extra)]);
        $this->scopeToCurrentShop();

        return parent::incrementEach($columns, $extra);
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        $this->assertShopIdIsImmutable([...array_keys($columns), ...array_keys($extra)]);
        $this->scopeToCurrentShop();

        return parent::decrementEach($columns, $extra);
    }

    public function updateOrInsert(array $attributes, array|callable $values = []): bool
    {
        throw new LogicException('Bulk user updateOrInsert is not supported; use the audited user lifecycle.');
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw new LogicException('Bulk user upserts are not supported; create or update User models explicitly.');
    }

    public function insert(array $values)
    {
        throw new LogicException('Bulk user inserts are not supported; create User models explicitly.');
    }

    public function insertOrIgnore(array $values)
    {
        throw new LogicException('Bulk user inserts are not supported; create User models explicitly.');
    }

    public function insertGetId(array $values, $sequence = null)
    {
        throw new LogicException('User inserts must use model persistence so model events are enforced.');
    }

    public function insertUsing(array $columns, $query)
    {
        throw new LogicException('Bulk user inserts are not supported; create User models explicitly.');
    }

    public function insertOrIgnoreUsing(array $columns, $query)
    {
        throw new LogicException('Bulk user inserts are not supported; create User models explicitly.');
    }

    public function insertOrIgnoreReturning(array $values, array $returning = ['*'], array|string|null $uniqueBy = null)
    {
        throw new LogicException('Bulk user inserts are not supported; create User models explicitly.');
    }

    private function scopeToCurrentShop(): void
    {
        $model = $this->getModel();
        $shopId = app(CurrentShop::class)->id();

        if ($shopId === null && $model->getKey() !== null) {
            $shopId = $model->getRawOriginal('shop_id') ?? $model->getAttribute('shop_id');

            if ($shopId === null) {
                $this->whereKey($model->getKey());

                return;
            }
        }

        if ($shopId === null) {
            throw new LogicException('A current shop context is required for bulk user mutations.');
        }

        $this->where($model->qualifyColumn('shop_id'), $shopId);
    }

    private function assertShopIdIsImmutable(array $columns): void
    {
        foreach ($columns as $column) {
            if (! is_string($column)) {
                continue;
            }

            $normalized = strtolower(str_replace(['`', '"'], '', $column));
            $segments = explode('.', $normalized);

            if (trim((string) end($segments)) === 'shop_id') {
                throw new AuthorizationException('A user cannot be reassigned to another shop.');
            }
        }
    }
}
