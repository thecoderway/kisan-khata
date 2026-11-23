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

        if (array_key_exists('shop_id', $values) && (int) $values['shop_id'] !== $shopId) {
            throw new AuthorizationException('A record cannot be moved to another shop.');
        }

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

        $shopId = $this->requireCurrentShopId();

        return $this->where(
            $this->getModel()->qualifyColumn('shop_id'),
            $shopId,
        )->toBase()->delete();
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
