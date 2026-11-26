<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentShop;
use App\Models\Concerns\SetsCreatedAt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class PaymentAllocation extends Model
{
    use BelongsToCurrentShop, SetsCreatedAt;

    public const TYPE_NORMAL_FIFO = 'NORMAL_FIFO';

    public const TYPE_SALE_RESTRICTED = 'SALE_RESTRICTED';

    public const TYPE_REVERSAL = 'REVERSAL';

    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'payment_id',
        'allocation_type',
        'waterfall_policy_version_id',
        'allocation_effective_at',
        'total_allocated',
        'reversal_of_allocation_id',
    ];

    protected function casts(): array
    {
        return [
            'allocation_effective_at' => 'immutable_datetime',
            'total_allocated' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment allocations are append-only.'));
        static::deleting(fn () => throw new LogicException('Payment allocations cannot be deleted.'));
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

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<PaymentAllocation, $this> */
    public function reversedAllocation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_allocation_id');
    }

    /** @return HasMany<PaymentAllocationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PaymentAllocationItem::class, 'allocation_id');
    }
}
