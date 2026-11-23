<?php

namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Prevent bulk Eloquent mutations from changing a user's tenant association.
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

        return parent::update($values);
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->assertShopIdIsImmutable([$column, ...array_keys($extra)]);

        return parent::increment($column, $amount, $extra);
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->assertShopIdIsImmutable([$column, ...array_keys($extra)]);

        return parent::decrement($column, $amount, $extra);
    }

    public function incrementEach(array $columns, array $extra = [])
    {
        $this->assertShopIdIsImmutable([...array_keys($columns), ...array_keys($extra)]);

        return parent::incrementEach($columns, $extra);
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        $this->assertShopIdIsImmutable([...array_keys($columns), ...array_keys($extra)]);

        return parent::decrementEach($columns, $extra);
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        $this->assertShopIdIsImmutable(is_array($update) ? $update : []);

        foreach ($values as $row) {
            if (is_array($row) && array_key_exists('shop_id', $row)) {
                throw new AuthorizationException('Bulk user inserts cannot assign or change shop ownership.');
            }
        }

        return parent::upsert($values, $uniqueBy, $update);
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
