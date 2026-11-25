<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IdempotencyKey extends Model
{
    use BelongsToCurrentShop;

    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_FAILED = 'FAILED';

    protected $fillable = [
        'operation_type',
        'key_hash',
        'request_hash',
        'status',
        'resource_type',
        'resource_id',
        'response_code',
    ];

    protected function casts(): array
    {
        return [
            'resource_id' => 'integer',
            'response_code' => 'integer',
        ];
    }

    /** @return BelongsTo<Shop, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** @return HasOne<FinancialTransaction, $this> */
    public function financialTransaction(): HasOne
    {
        return $this->hasOne(FinancialTransaction::class);
    }
}
