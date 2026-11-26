<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentShop;
use App\Models\Concerns\SetsCreatedAt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InterestCalculationItem extends Model
{
    use BelongsToCurrentShop, SetsCreatedAt;

    protected $fillable = [
        'customer_id', 'calculation_run_id', 'sale_id', 'source_interest_lot_id',
        'shop_policy_version_id', 'customer_policy_version_id', 'interval_start_date',
        'interval_end_date_exclusive', 'actual_days', 'grace_days_applied',
        'monthly_rate_percent', 'eligible_principal_basis', 'unrounded_interest',
        'posted_interest', 'capitalization_enabled', 'item_key_hash', 'ledger_entry_id',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'interval_start_date' => 'date', 'interval_end_date_exclusive' => 'date',
            'actual_days' => 'integer', 'grace_days_applied' => 'integer',
            'monthly_rate_percent' => 'decimal:8', 'eligible_principal_basis' => 'decimal:8',
            'unrounded_interest' => 'decimal:8', 'posted_interest' => 'decimal:2',
            'capitalization_enabled' => 'boolean', 'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Interest calculation items are append-only.'));
        static::deleting(fn () => throw new LogicException('Interest calculation items cannot be deleted.'));
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(InterestCalculationRun::class, 'calculation_run_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
