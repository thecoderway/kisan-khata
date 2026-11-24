<?php

namespace App\Tenancy;

use InvalidArgumentException;
use LogicException;

class TenantPrivatePath
{
    public function __construct(private readonly CurrentShop $currentShop) {}

    public function make(string $path): string
    {
        $shopId = $this->currentShop->id();

        if ($shopId === null) {
            throw new LogicException('A current shop context is required for private tenant files.');
        }

        $path = str_replace('\\', '/', trim($path));

        if ($path === '' || str_starts_with($path, '/') || str_contains($path, "\0")) {
            throw new InvalidArgumentException('A relative private file path is required.');
        }

        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('Private file paths cannot contain traversal segments.');
            }
        }

        return "shops/{$shopId}/".implode('/', $segments);
    }
}
