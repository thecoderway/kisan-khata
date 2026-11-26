<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

trait SetsCreatedAt
{
    protected static function bootSetsCreatedAt(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('created_at') === null) {
                $model->setAttribute('created_at', now());
            }
        });
    }
}
