<?php

namespace App\Models\Concerns;

use App\Tenancy\CurrentShop;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

trait BelongsToCurrentShop
{
    protected static function bootBelongsToCurrentShop(): void
    {
        static::addGlobalScope('current_shop', function (Builder $query): void {
            $currentShopId = app(CurrentShop::class)->id();

            if ($currentShopId === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where(
                $query->getModel()->qualifyColumn('shop_id'),
                $currentShopId,
            );
        });

        static::creating(function (Model $model): void {
            $currentShopId = self::currentShopId();
            $requestedShopId = $model->getAttribute('shop_id');

            if ($requestedShopId !== null && (int) $requestedShopId !== $currentShopId) {
                throw new AuthorizationException('A record cannot be created for another shop.');
            }

            $model->setAttribute('shop_id', $currentShopId);
        });

        static::updating(function (Model $model): void {
            self::assertCurrentOwnership($model);

            if ($model->isDirty('shop_id')) {
                throw new AuthorizationException('A record cannot be moved to another shop.');
            }
        });

        static::deleting(function (Model $model): void {
            self::assertCurrentOwnership($model);
        });
    }

    private static function currentShopId(): int
    {
        $shopId = app(CurrentShop::class)->id();

        if ($shopId === null) {
            throw new LogicException('A current shop context is required for tenant-owned records.');
        }

        return $shopId;
    }

    private static function assertCurrentOwnership(Model $model): void
    {
        $currentShopId = self::currentShopId();

        if ((int) $model->getOriginal('shop_id') !== $currentShopId) {
            throw new AuthorizationException('The record does not belong to the current shop.');
        }
    }
}
