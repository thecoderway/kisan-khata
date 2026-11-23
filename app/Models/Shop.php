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
        'name',
        'owner_name',
        'mobile',
        'email',
        'address',
        'village',
        'tehsil',
        'district',
        'state',
        'pincode',
        'logo_path',
        'timezone',
        'preferred_language',
        'status',
    ];

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<ShopSetting, $this>
     */
    public function settings(): HasMany
    {
        return $this->hasMany(ShopSetting::class);
    }
}
