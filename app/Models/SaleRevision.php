<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SaleRevision extends Model
{
    use BelongsToCurrentShop;

    public $timestamps = false;

    protected $fillable = [
        'sale_id',
        'revision_number',
        'old_values',
        'new_values',
        'correction_reason',
        'changed_by',
        'changed_at',
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
        static::updating(fn () => throw new LogicException('Sale revision history is append-only.'));
        static::deleting(fn () => throw new LogicException('Sale revision history cannot be deleted.'));
    }

    /** @return BelongsTo<Shop, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
