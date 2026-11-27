<?php

namespace App\Models;

use App\Models\Builders\ProtectedFinancialRecordBuilder;
use App\Models\Concerns\BelongsToCurrentShop;
use App\Models\Concerns\SetsCreatedAt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class InterestCalculationRun extends Model
{
    use BelongsToCurrentShop, SetsCreatedAt;

    protected $fillable = [
        'customer_id', 'trigger_type', 'trigger_payment_id', 'settlement_run_id',
        'cutoff_date_exclusive', 'event_key_hash', 'started_at', 'completed_at',
        'status', 'idempotency_key_id',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'cutoff_date_exclusive' => 'date', 'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime',
        ];
    }

    public function newEloquentBuilder($query): ProtectedFinancialRecordBuilder
    {
        return new ProtectedFinancialRecordBuilder($query);
    }

    protected static function booted(): void
    {
        static::updating(function (InterestCalculationRun $run): void {
            if ($run->getOriginal('status') === 'COMPLETED') {
                throw new LogicException('Completed interest calculation runs are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Interest calculation runs cannot be deleted.'));
    }

    public function items(): HasMany
    {
        return $this->hasMany(InterestCalculationItem::class, 'calculation_run_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function triggerPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'trigger_payment_id');
    }

    public function settlementRun(): BelongsTo
    {
        return $this->belongsTo(InterestSettlementRun::class, 'settlement_run_id');
    }
}
