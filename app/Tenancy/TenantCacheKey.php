<?php

namespace App\Tenancy;

use LogicException;

class TenantCacheKey
{
    public function __construct(private readonly CurrentShop $currentShop) {}

    public function make(string $key): string
    {
        $shopId = $this->currentShop->id();

        if ($shopId === null) {
            throw new LogicException('A current shop context is required for tenant cache keys.');
        }

        $key = trim($key, ':');

        if ($key === '' || str_contains($key, '..')) {
            throw new LogicException('A valid tenant cache key is required.');
        }

        return "shop:{$shopId}:{$key}";
    }
}
