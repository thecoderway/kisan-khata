<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentShop;
use App\Models\Concerns\SetsCreatedAt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Payment extends Model
{
    use BelongsToCurrentShop, SetsCreatedAt;

    public const TYPE_NORMAL = 'NORMAL';

    public const TYPE_SALE_DAY_AUTO = 'SALE_DAY_AUTO';

    public const TYPE_REVERSAL = 'REVERSAL';

    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'payment_type',
        'source_sale_id',
        'payment_date',
        'payment_method',
        'reference_number',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'posted_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Posted Payments are immutable; use reversal and optional replacement.'));
        static::deleting(fn () => throw new LogicException('Posted Payments cannot be deleted.'));
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

    /** @return BelongsTo<Sale, $this> */
    public function sourceSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'source_sale_id');
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function reversedPayment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_payment_id');
    }

    /** @return BelongsTo<Payment, $this> */
    public function replacementForPayment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replacement_for_payment_id');
    }

    /** @return HasMany<PaymentRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(PaymentRevision::class);
    }
}
