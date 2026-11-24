<?php

namespace App\Tenancy\Middleware;

use App\Contracts\TenantAwareJob;
use App\Models\Shop;
use App\Tenancy\CurrentShop;
use Closure;
use LogicException;

class ResolveTenantForJob
{
    public function __construct(private readonly CurrentShop $currentShop) {}

    public function handle(TenantAwareJob $job, Closure $next): mixed
    {
        $shop = Shop::query()
            ->whereKey($job->tenantShopId())
            ->where('status', Shop::STATUS_ACTIVE)
            ->first();

        if (! $shop) {
            throw new LogicException('The tenant-aware job requires an existing active shop.');
        }

        $this->currentShop->set($shop);

        try {
            return $next($job);
        } finally {
            $this->currentShop->clear();
        }
    }
}
