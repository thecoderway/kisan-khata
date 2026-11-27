<?php

namespace App\Models;

use App\Models\Builders\ProtectedFinancialRecordBuilder;
use App\Models\Concerns\BelongsToCurrentShop;
use App\Models\Concerns\SetsCreatedAt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PaymentAllocationItem extends Model
{
    use BelongsToCurrentShop, SetsCreatedAt;

    public const COMPONENT_PRINCIPAL = 'PRINCIPAL';

    public const COMPONENT_NON_CAPITALIZED_INTEREST = 'NON_CAPITALIZED_INTEREST';

    public const COMPONENT_CAPITALIZED_INTEREST_PRINCIPAL = 'CAPITALIZED_INTEREST_PRINCIPAL';

    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'allocation_id',
        'payment_id',
        'sale_id',
        'component_type',
        'source_interest_lot_id',
        'amount',
        'sale_fifo_sequence',
        'component_sequence',
        'lot_sequence',
        'reverses_allocation_item_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'sale_fifo_sequence' => 'integer',
            'component_sequence' => 'integer',
            'lot_sequence' => 'integer',
        ];
    }

    public function newEloquentBuilder($query): ProtectedFinancialRecordBuilder
    {
        return new ProtectedFinancialRecordBuilder($query);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment allocation items are append-only.'));
        static::deleting(fn () => throw new LogicException('Payment allocation items cannot be deleted.'));
    }

    /** @return BelongsTo<Shop, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<PaymentAllocation, $this> */
    public function allocation(): BelongsTo
    {
        return $this->belongsTo(PaymentAllocation::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<PaymentAllocationItem, $this> */
    public function reversedItem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_allocation_item_id');
    }
}
