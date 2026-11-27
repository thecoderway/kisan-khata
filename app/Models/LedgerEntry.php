<?php

namespace App\Models;

use App\Models\Builders\ProtectedFinancialRecordBuilder;
use App\Models\Concerns\BelongsToCurrentShop;
use App\Models\Concerns\SetsCreatedAt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class LedgerEntry extends Model
{
    use BelongsToCurrentShop, SetsCreatedAt;

    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'financial_transaction_id',
        'sale_id',
        'payment_id',
        'component',
        'entry_kind',
        'debit_amount',
        'credit_amount',
        'effective_date',
        'sequence_number',
        'reverses_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'debit_amount' => 'decimal:2',
            'credit_amount' => 'decimal:2',
            'effective_date' => 'date',
            'sequence_number' => 'integer',
        ];
    }

    public function newEloquentBuilder($query): ProtectedFinancialRecordBuilder
    {
        return new ProtectedFinancialRecordBuilder($query);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Ledger entries cannot be deleted.'));
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

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<LedgerEntry, $this> */
    public function reversedEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }
}
