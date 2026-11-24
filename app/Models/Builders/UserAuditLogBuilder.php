<?php

namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Enforce append-only behavior for all Eloquent query-builder mutation paths.
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

    public function updateOrInsert(array $attributes, array $values = [])
    {
        throw new LogicException('User audit records are append-only.');
    }
}
