<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAccount extends Model
{
    use BelongsToCurrentShop;

    protected $fillable = [
        'customer_id',
        'last_interest_cutoff_date',
        'last_financial_event_at',
        'projection_version',
    ];

    protected function casts(): array
    {
        return [
            'principal_balance' => 'decimal:2',
            'interest_balance' => 'decimal:2',
            'total_payable' => 'decimal:2',
            'last_interest_cutoff_date' => 'date',
            'last_financial_event_at' => 'immutable_datetime',
            'projection_version' => 'integer',
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
}
