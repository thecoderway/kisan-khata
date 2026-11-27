<?php

namespace App\Models;

use App\Models\Builders\ProtectedFinancialRecordBuilder;
use App\Models\Concerns\BelongsToCurrentShop;
use App\Models\Concerns\SetsCreatedAt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class InterestSettlementRun extends Model
{
    use BelongsToCurrentShop, SetsCreatedAt;

    protected $fillable = [
        'customer_id', 'settlement_date', 'cutoff_date_exclusive', 'shop_policy_version_id',
        'status', 'idempotency_key_id', 'started_at', 'completed_at',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'settlement_date' => 'date', 'cutoff_date_exclusive' => 'date',
            'started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function newEloquentBuilder($query): ProtectedFinancialRecordBuilder
    {
        return new ProtectedFinancialRecordBuilder($query);
    }

    protected static function booted(): void
    {
        static::updating(function (InterestSettlementRun $run): void {
            if ($run->getOriginal('status') === 'COMPLETED') {
                throw new LogicException('Completed settlement runs are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Settlement runs cannot be deleted.'));
    }

    public function items(): HasMany
    {
        return $this->hasMany(InterestSettlementItem::class, 'settlement_run_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
