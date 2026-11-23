<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use App\Tenancy\CurrentShop;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentShop
{
    public function __construct(private readonly CurrentShop $currentShop) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403, 'An active user account is required.');
        }

        $shop = $user->shop()
            ->where('status', Shop::STATUS_ACTIVE)
            ->first();

        if (! $shop) {
            abort(403, 'An active shop is required.');
        }

        $this->currentShop->set($shop);

        return $next($request);
    }
}
