<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'name', 'owner_name', 'mobile', 'email', 'address',
        'village', 'tehsil', 'district', 'state',
        'state_code', 'district_code', 'subdistrict_code', 'village_code',
        'pincode', 'logo_path', 'timezone', 'preferred_language',
    ];

    /** @return HasMany<CustomerAccount, $this> */
    public function customerAccounts(): HasMany
    {
        return $this->hasMany(CustomerAccount::class);
    }

    /** @return HasMany<FinancialTransaction, $this> */
    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    /** @return HasMany<IdempotencyKey, $this> */
    public function idempotencyKeys(): HasMany
    {
        return $this->hasMany(IdempotencyKey::class);
    }

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

    /** @return HasMany<InterestPolicy, $this> */
    public function interestPolicies(): HasMany
    {
        return $this->hasMany(InterestPolicy::class);
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

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<ShopSetting, $this> */
    public function settings(): HasMany
    {
        return $this->hasMany(ShopSetting::class);
    }
}
