<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToCurrentShop, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'customer_code', 'name_original', 'name_original_language', 'name_hi', 'name_en',
        'mobile', 'state_code', 'district_code', 'subdistrict_code', 'village_code',
        'address_original', 'address_original_language', 'address_hi', 'address_en', 'notes',
    ];

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** @return HasMany<CustomerInterestPolicyVersion, $this> */
    public function interestPolicyVersions(): HasMany
    {
        return $this->hasMany(CustomerInterestPolicyVersion::class);
    }

    /** @return HasMany<InterestCalculationRun, $this> */
    public function interestCalculationRuns(): HasMany
    {
        return $this->hasMany(InterestCalculationRun::class);
    }

    /** @return HasMany<InterestLot, $this> */
    public function interestLots(): HasMany
    {
        return $this->hasMany(InterestLot::class);
    }

    /** @return HasMany<InterestSettlementRun, $this> */
    public function interestSettlementRuns(): HasMany
    {
        return $this->hasMany(InterestSettlementRun::class);
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /** @return HasMany<AccountBalanceSnapshot, $this> */
    public function balanceSnapshots(): HasMany
    {
        return $this->hasMany(AccountBalanceSnapshot::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Shop, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
