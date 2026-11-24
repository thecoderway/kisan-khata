<?php

use App\Http\Controllers\Api\Auth\MobileOtpController;
use App\Http\Controllers\Api\Auth\TokenController;
use App\Http\Controllers\Api\Users\ShopUserLifecycleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth/mobile')->group(function (): void {
    Route::post('/request-otp', [MobileOtpController::class, 'request'])->middleware('throttle:20,1');
    Route::post('/verify-otp', [MobileOtpController::class, 'verify'])->middleware('throttle:20,1');
});

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::post('/logout', [TokenController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'abilities:first-login'])->group(function (): void {
    Route::post('/auth/mobile/complete-first-login', [MobileOtpController::class, 'completeFirstLogin'])
        ->middleware('throttle:5,1');
});

Route::middleware(['auth:sanctum', 'abilities:app', 'tenant'])->group(function (): void {
    Route::get('/user', function (Request $request) {
        return $request->user()->load('shop:id,name');
    });

    Route::prefix('shop/users')->group(function (): void {
        Route::post('/{user}/deactivate', [ShopUserLifecycleController::class, 'deactivate']);
        Route::post('/{user}/reactivate', [ShopUserLifecycleController::class, 'reactivate']);
        Route::patch('/{user}/role', [ShopUserLifecycleController::class, 'changeRole']);
    });
});
