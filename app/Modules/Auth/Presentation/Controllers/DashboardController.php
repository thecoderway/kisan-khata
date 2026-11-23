<?php

namespace App\\Modules\\Auth\\Presentation\\Controllers;

use Illuminate\\View\\View;

class DashboardController
{
    public function __invoke(): View
    {
        return view('dashboard');
    }
}
