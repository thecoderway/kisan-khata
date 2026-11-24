<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class UserAuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('User audit records are append-only.'));
        static::deleting(fn () => throw new LogicException('User audit records are append-only.'));
    }
}
