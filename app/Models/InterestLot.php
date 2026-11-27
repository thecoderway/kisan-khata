<?php

namespace App\Models;

use App\Models\Builders\ProtectedFinancialRecordBuilder;
use App\Models\Concerns\BelongsToCurrentShop;
use App\Models\Concerns\SetsCreatedAt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class InterestLot extends Model
{
    use BelongsToCurrentShop, SetsCreatedAt;

    protected $fillable = [
        'customer_id', 'sale_id', 'calculation_item_id', 'lot_type', 'lot_date', 'original_amount',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['lot_date' => 'date', 'original_amount' => 'decimal:2', 'created_at' => 'immutable_datetime'];
    }

    public function newEloquentBuilder($query): ProtectedFinancialRecordBuilder
    {
        return new ProtectedFinancialRecordBuilder($query);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Interest lots are immutable.'));
        static::deleting(fn () => throw new LogicException('Interest lots cannot be deleted.'));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function calculationItem(): BelongsTo
    {
        return $this->belongsTo(InterestCalculationItem::class, 'calculation_item_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(InterestLotEvent::class);
    }
}
