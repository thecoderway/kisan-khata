<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopSetting extends Model
{
    use BelongsToCurrentShop;

    protected $fillable = [
        'setting_key',
        'setting_value',
    ];

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
