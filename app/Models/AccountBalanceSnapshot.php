<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentShop;
use App\Models\Concerns\SetsCreatedAt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountBalanceSnapshot extends Model
{
    use BelongsToCurrentShop, SetsCreatedAt;

    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'as_of_date',
        'principal_balance',
        'interest_balance',
        'total_payable',
        'source_ledger_entry_id',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'as_of_date' => 'date',
            'principal_balance' => 'decimal:2',
            'interest_balance' => 'decimal:2',
            'total_payable' => 'decimal:2',
            'calculated_at' => 'immutable_datetime',
        ];
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

    /** @return BelongsTo<LedgerEntry, $this> */
    public function sourceLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'source_ledger_entry_id');
    }
}
