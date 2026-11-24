<?php

namespace App\Tenancy;

use App\Models\Shop;

class CurrentShop
{
    private ?Shop $shop = null;

    public function set(Shop $shop): void
    {
        $this->shop = $shop;
    }

    public function get(): ?Shop
    {
        return $this->shop;
    }

    public function id(): ?int
    {
        return $this->shop?->getKey();
    }

    public function clear(): void
    {
        $this->shop = null;
    }

    public function require(): Shop
    {
        return $this->shop ?? throw new \LogicException('The current shop context has not been resolved.');
    }
}
