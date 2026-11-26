<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentShop;
use App\Models\Concerns\SetsCreatedAt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CustomerInterestPolicyVersion extends Model
{
    use BelongsToCurrentShop, SetsCreatedAt;

    protected $fillable = [
        'customer_id', 'version_number', 'effective_from', 'effective_to',
        'monthly_rate_percent', 'capitalization_enabled', 'created_by',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'version_number' => 'integer', 'effective_from' => 'date', 'effective_to' => 'date',
            'monthly_rate_percent' => 'decimal:8', 'capitalization_enabled' => 'boolean',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (CustomerInterestPolicyVersion $version): void {
            if (array_keys($version->getDirty()) !== ['effective_to']
                || $version->getOriginal('effective_to') !== null
                || $version->getAttribute('effective_to') === null
                || $version->getAttribute('effective_to') <= $version->getAttribute('effective_from')) {
                throw new LogicException('Customer policy versions are immutable; only an open version may be closed once.');
            }
        });
        static::deleting(fn () => throw new LogicException('Customer policy versions cannot be deleted.'));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
