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
