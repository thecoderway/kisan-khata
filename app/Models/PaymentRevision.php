<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PaymentRevision extends Model
{
    use BelongsToCurrentShop;

    public const TYPE_REVERSAL = 'REVERSAL';

    public const TYPE_REPLACEMENT = 'REPLACEMENT';

    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'payment_id',
        'revision_number',
        'revision_type',
        'old_values',
        'new_values',
        'correction_reason',
        'changed_by',
        'changed_at',
        'reversal_payment_id',
        'replacement_payment_id',
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'old_values' => 'array',
            'new_values' => 'array',
            'changed_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment revision history is append-only.'));
        static::deleting(fn () => throw new LogicException('Payment revision history cannot be deleted.'));
    }

    /** @return BelongsTo<Shop, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /** @return BelongsTo<Payment, $this> */
    public function reversalPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'reversal_payment_id');
    }

    /** @return BelongsTo<Payment, $this> */
    public function replacementPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'replacement_payment_id');
    }
}
