<?php

namespace App\Providers;

use App\Tenancy\CurrentShop;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentShop::class, fn (): CurrentShop => new CurrentShop);
    }

    public function boot(): void
    {
        //
    }
}
