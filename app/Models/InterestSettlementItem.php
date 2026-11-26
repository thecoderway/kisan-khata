<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentShop;
use App\Models\Concerns\SetsCreatedAt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InterestSettlementItem extends Model
{
    use BelongsToCurrentShop, SetsCreatedAt;

    protected $fillable = [
        'customer_id', 'settlement_run_id', 'calculation_item_id', 'sale_id',
        'interest_lot_id', 'posted_amount', 'ledger_entry_id',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['posted_amount' => 'decimal:2', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Interest settlement items are append-only.'));
        static::deleting(fn () => throw new LogicException('Interest settlement items cannot be deleted.'));
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(InterestSettlementRun::class, 'settlement_run_id');
    }

    public function calculationItem(): BelongsTo
    {
        return $this->belongsTo(InterestCalculationItem::class, 'calculation_item_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
