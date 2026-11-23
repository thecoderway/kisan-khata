<?php

namespace App\Modules\Auth\Presentation\Controllers;

use App\Tenancy\CurrentShop;
use Illuminate\View\View;

class DashboardController
{
    public function __invoke(CurrentShop $currentShop): View
    {
        return view('dashboard', [
            'shop' => $currentShop->require(),
        ]);
    }
}
