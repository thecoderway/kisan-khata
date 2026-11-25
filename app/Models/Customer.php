<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToCurrentShop, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    protected $fillable = [
        'customer_code', 'name_original', 'name_original_language', 'name_hi', 'name_en',
        'mobile', 'state_code', 'district_code', 'subdistrict_code', 'village_code',
        'address_original', 'address_original_language', 'address_hi', 'address_en', 'notes',
    ];

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
