<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class FinancialTransaction extends Model
{
    use BelongsToCurrentShop;

    public const TYPE_SALE_POSTING = 'SALE_POSTING';

    public const TYPE_PAYMENT_POSTING = 'PAYMENT_POSTING';

    public const TYPE_INTEREST_POSTING = 'INTEREST_POSTING';

    public const TYPE_CAPITALIZATION = 'CAPITALIZATION';

    public const TYPE_REVERSAL = 'REVERSAL';

    public $timestamps = false;

    // Server-controlled identity, actor, idempotency and posting fields must be
    // assigned explicitly by the posting service, never mass-assigned from input.
    protected $fillable = [
        'customer_id',
        'transaction_type',
        'effective_date',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'occurred_at' => 'immutable_datetime',
            'posted_at' => 'immutable_datetime',
            'source_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FinancialTransaction $transaction): void {
            $transaction->setAttribute('created_at', $transaction->getAttribute('created_at') ?? now());
        });

        static::updating(fn () => throw new LogicException('Posted financial transaction headers are immutable.'));
        static::deleting(fn () => throw new LogicException('Posted financial transaction headers cannot be deleted.'));
    }

    /** @return HasOne<Sale, $this> */
    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }

    /** @return HasOne<Payment, $this> */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /** @return BelongsTo<IdempotencyKey, $this> */
    public function idempotencyKey(): BelongsTo
    {
        return $this->belongsTo(IdempotencyKey::class);
    }
}
