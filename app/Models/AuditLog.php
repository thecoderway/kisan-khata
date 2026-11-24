<?php

namespace App\Models;

use App\Models\Builders\AuditLogBuilder;
use App\Tenancy\CurrentShop;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function newEloquentBuilder($query): AuditLogBuilder
    {
        return new AuditLogBuilder($query);
    }

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('current_shop', function (Builder $query): void {
            $shopId = app(CurrentShop::class)->id();

            if ($shopId === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where($query->getModel()->qualifyColumn('shop_id'), $shopId);
        });

        static::creating(function (AuditLog $log): void {
            $shopId = app(CurrentShop::class)->id();

            if ($shopId === null) {
                throw new LogicException('A current shop context is required for tenant audit records.');
            }

            if ($log->shop_id !== null && (int) $log->shop_id !== $shopId) {
                throw new AuthorizationException('An audit record cannot be created for another shop.');
            }

            $log->shop_id = $shopId;
        });

        static::updating(fn () => throw new LogicException('Audit records are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit records are append-only.'));
    }
}
