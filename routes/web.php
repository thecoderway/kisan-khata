<?php

use App\Modules\Auth\Presentation\Controllers\AuthenticatedSessionController;
use App\Modules\Auth\Presentation\Controllers\DashboardController;
use App\Modules\Auth\Presentation\Controllers\ForcePasswordChangeController;
use App\Modules\Auth\Presentation\Controllers\NewPasswordController;
use App\Modules\Auth\Presentation\Controllers\PasswordResetLinkController;
use App\Modules\Auth\Presentation\Controllers\RegisteredShopController;
use App\Modules\Users\Presentation\Controllers\Web\UserAuditHistoryController;
use App\Modules\Users\Presentation\Controllers\Web\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login'));

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisteredShopController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredShopController::class, 'store'])->middleware('throttle:5,1');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:3,1')->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:5,1')->name('password.store');
});

Route::middleware(['auth', 'password.changed'])->group(function (): void {
    Route::get('/first-login/password', [ForcePasswordChangeController::class, 'edit'])
        ->name('password.first-login.edit');
    Route::post('/first-login/password', [ForcePasswordChangeController::class, 'update'])
        ->middleware('throttle:5,1')->name('password.first-login.update');
});

Route::middleware(['auth', 'password.changed', 'tenant'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('users')->name('users.')->group(function (): void {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');
        Route::get('/create', [UserManagementController::class, 'create'])->name('create');
        Route::post('/', [UserManagementController::class, 'store'])->middleware('throttle:10,1')->name('store');
        Route::get('/audit-history', UserAuditHistoryController::class)->name('audit');
        Route::post('/{user}/deactivate', [UserManagementController::class, 'deactivate'])->name('deactivate');
        Route::post('/{user}/reactivate', [UserManagementController::class, 'reactivate'])->name('reactivate');
        Route::patch('/{user}/role', [UserManagementController::class, 'changeRole'])->name('role');
    });
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
