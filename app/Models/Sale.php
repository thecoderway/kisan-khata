<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentShop;
use App\Models\Concerns\SetsCreatedAt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class Sale extends Model
{
    use BelongsToCurrentShop, SetsCreatedAt;

    public const PAYMENT_STATUS_UNPAID = 'UNPAID';

    public const PAYMENT_STATUS_PARTIALLY_PAID = 'PARTIALLY_PAID';

    public const PAYMENT_STATUS_PAID = 'PAID';

    public const INTEREST_STATUS_PENDING = 'PENDING';

    public const INTEREST_STATUS_SETTLED = 'SETTLED';

    public const PAYMENT_CHOICE_PAID = 'PAID';

    public const PAYMENT_CHOICE_UNPAID = 'UNPAID';

    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'sale_date',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'amount' => 'decimal:2',
            'posted_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Sale $sale): void {
            if ($sale->posted_at !== null) {
                throw new LogicException('Posted Sales are immutable; use an approved Sale revision workflow.');
            }
        });
        static::deleting(function (Sale $sale): void {
            if ($sale->posted_at !== null) {
                throw new LogicException('Posted Sales cannot be deleted.');
            }
        });
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

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
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

    /** @return HasMany<SaleRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(SaleRevision::class);
    }

    /** @return HasMany<InterestCalculationItem, $this> */
    public function interestCalculationItems(): HasMany
    {
        return $this->hasMany(InterestCalculationItem::class);
    }

    /** @return HasMany<InterestLot, $this> */
    public function interestLots(): HasMany
    {
        return $this->hasMany(InterestLot::class);
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /** @return HasMany<PaymentAllocationItem, $this> */
    public function allocationItems(): HasMany
    {
        return $this->hasMany(PaymentAllocationItem::class);
    }

    /** @return HasOne<Payment, $this> */
    public function saleDayPayment(): HasOne
    {
        return $this->hasOne(Payment::class, 'source_sale_id');
    }
}
