<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentShop;
use App\Models\Concerns\SetsCreatedAt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InterestLotEvent extends Model
{
    use BelongsToCurrentShop, SetsCreatedAt;

    protected $fillable = [
        'customer_id', 'interest_lot_id', 'event_type', 'event_date', 'amount',
        'financial_transaction_id', 'allocation_item_id', 'reverses_event_id', 'event_key_hash',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['event_date' => 'date', 'amount' => 'decimal:2', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Interest lot events are append-only.'));
        static::deleting(fn () => throw new LogicException('Interest lot events cannot be deleted.'));
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(InterestLot::class, 'interest_lot_id');
    }

    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    public function allocationItem(): BelongsTo
    {
        return $this->belongsTo(PaymentAllocationItem::class, 'allocation_item_id');
    }
}
