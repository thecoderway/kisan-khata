<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InterestPolicy extends Model
{
    use BelongsToCurrentShop;

    protected $fillable = ['policy_code'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(InterestPolicyVersion::class);
    }
}
