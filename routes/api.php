<?php

use App\Http\Controllers\Api\Auth\MobileOtpController;
use App\Http\Controllers\Api\Auth\TokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth/mobile')->group(function (): void {
    Route::post('/request-otp', [MobileOtpController::class, 'request'])->middleware('throttle:20,1');
    Route::post('/verify-otp', [MobileOtpController::class, 'verify'])->middleware('throttle:20,1');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', function (Request $request) {
        return $request->user()->load('shop:id,name');
    })->middleware('tenant');

    Route::post('/logout', [TokenController::class, 'destroy']);
});
