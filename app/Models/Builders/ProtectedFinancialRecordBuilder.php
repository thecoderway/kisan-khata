<?php

namespace App\Models\Builders;

use LogicException;

/**
 * Prevent set-based Eloquent writes from bypassing financial record lifecycle
 * rules and per-model immutability events.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends TenantOwnedBuilder<TModel>
 */
class ProtectedFinancialRecordBuilder extends TenantOwnedBuilder
{
    private function rejectBulkMutation(): never
    {
        throw new LogicException('Bulk Eloquent mutations are disabled for protected financial records; use an approved lifecycle service.');
    }

    public function insert(array $values)
    {
        $this->rejectBulkMutation();
    }

    public function insertOrIgnore(array $values)
    {
        $this->rejectBulkMutation();
    }

    private bool $allowModelUpdate = false;

    public function updateModelRecord(array $values)
    {
        $this->allowModelUpdate = true;

        try {
            return parent::update($values);
        } finally {
            $this->allowModelUpdate = false;
        }
    }

    public function update(array $values)
    {
        if ($this->allowModelUpdate) {
            return parent::update($values);
        }

        $this->rejectBulkMutation();
    }

    public function delete($id = null)
    {
        $this->rejectBulkMutation();
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        $this->rejectBulkMutation();
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->rejectBulkMutation();
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->rejectBulkMutation();
    }

    public function incrementEach(array $columns, array $extra = [])
    {
        $this->rejectBulkMutation();
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        $this->rejectBulkMutation();
    }

    public function touch($column = null)
    {
        $this->rejectBulkMutation();
    }

    public function updateOrInsert(array $attributes, array|callable $values = [])
    {
        $this->rejectBulkMutation();
    }

    public function truncate()
    {
        $this->rejectBulkMutation();
    }
}
