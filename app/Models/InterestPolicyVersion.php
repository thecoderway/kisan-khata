<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentShop;
use App\Models\Concerns\SetsCreatedAt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InterestPolicyVersion extends Model
{
    use BelongsToCurrentShop, SetsCreatedAt;

    protected $fillable = [
        'interest_policy_id', 'version_number', 'effective_from', 'effective_to',
        'monthly_rate_percent', 'grace_days', 'capitalization_enabled', 'payment_waterfall',
        'annual_settlement_month_1', 'annual_settlement_day_1',
        'annual_settlement_month_2', 'annual_settlement_day_2', 'created_by',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'version_number' => 'integer', 'effective_from' => 'date', 'effective_to' => 'date',
            'monthly_rate_percent' => 'decimal:8', 'grace_days' => 'integer',
            'capitalization_enabled' => 'boolean', 'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (InterestPolicyVersion $version): void {
            if (array_keys($version->getDirty()) !== ['effective_to']
                || $version->getOriginal('effective_to') !== null
                || $version->getAttribute('effective_to') === null
                || $version->getAttribute('effective_to') <= $version->getAttribute('effective_from')) {
                throw new LogicException('Policy versions are immutable; only an open version may be closed once.');
            }
        });
        static::deleting(fn () => throw new LogicException('Policy versions cannot be deleted.'));
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(InterestPolicy::class, 'interest_policy_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
