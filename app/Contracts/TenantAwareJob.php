<?php

namespace App\Contracts;

interface TenantAwareJob
{
    public function tenantShopId(): int;
}
