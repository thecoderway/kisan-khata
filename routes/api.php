<?php

use App\Http\Controllers\Api\Auth\MobileOtpController;
use App\Http\Controllers\Api\Auth\TokenController;
use App\Http\Controllers\Api\Users\ShopUserLifecycleController;
use App\Http\Controllers\Api\Users\UserAuditHistoryController;
use App\Modules\Customers\Presentation\Controllers\Api\CustomerController;
use App\Modules\Customers\Presentation\Controllers\CustomerLifecycleController;
use App\Modules\Financial\Presentation\Controllers\Api\CustomerLedgerController;
use App\Modules\Financial\Presentation\Controllers\Api\CustomerReconciliationController;
use App\Modules\Financial\Presentation\Controllers\Api\FinancialAdministrationController;
use App\Modules\Financial\Presentation\Controllers\Api\PaymentController;
use App\Modules\Financial\Presentation\Controllers\Api\PaymentCorrectionController;
use App\Modules\Financial\Presentation\Controllers\Api\SaleController;
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

Route::middleware(['auth:sanctum', 'abilities:app'])->group(function (): void {
    Route::middleware('tenant')->group(function (): void {
        Route::get('/user', function (Request $request) {
            return $request->user()->load('shop:id,name');
        });

        Route::get('/shop/users/audit-logs', UserAuditHistoryController::class);

        Route::prefix('shop/users')->group(function (): void {
            Route::post('/{user}/deactivate', [ShopUserLifecycleController::class, 'deactivate']);
            Route::post('/{user}/reactivate', [ShopUserLifecycleController::class, 'reactivate']);
            Route::patch('/{user}/role', [ShopUserLifecycleController::class, 'changeRole']);
        });

        Route::prefix('v1')->group(function (): void {
            Route::get('/sales', [SaleController::class, 'index']);
            Route::post('/sales', [SaleController::class, 'store']);
            Route::get('/sales/{sale}', [SaleController::class, 'show']);
            Route::post('/sales/{sale}/revision', [SaleController::class, 'revise']);
            Route::get('/payments', [PaymentController::class, 'index']);
            Route::post('/payments', [PaymentController::class, 'store']);
            Route::get('/payments/{payment}', [PaymentController::class, 'show']);
            Route::get('/customers', [CustomerController::class, 'index']);
            Route::post('/customers', [CustomerController::class, 'store']);
            Route::get('/customers/{customer}', [CustomerController::class, 'show']);
            Route::put('/customers/{customer}', [CustomerController::class, 'update']);
            Route::post('/customers/{customer}/deactivate', [CustomerLifecycleController::class, 'deactivate']);
            Route::post('/customers/{customer}/reactivate', [CustomerLifecycleController::class, 'reactivate']);
            Route::delete('/customers/{customer}', [CustomerLifecycleController::class, 'softDelete']);
            Route::post('/customers/{customer}/restore', [CustomerLifecycleController::class, 'restore']);
            Route::get('/customers/{customer}/ledger', [CustomerLedgerController::class, 'show']);
            Route::get('/reconciliation/customers/{customer}', [CustomerReconciliationController::class, 'show']);
            Route::post('/reconciliation/customers/{customer}/repair', [FinancialAdministrationController::class, 'repairCustomer']);
            Route::get('/interest-policy', [FinancialAdministrationController::class, 'shopPolicy']);
            Route::post('/interest-policy', [FinancialAdministrationController::class, 'updateShopPolicy']);
            Route::get('/customers/{customer}/interest-policy', [FinancialAdministrationController::class, 'customerPolicy']);
            Route::post('/customers/{customer}/interest-policy', [FinancialAdministrationController::class, 'updateCustomerPolicy']);
            Route::get('/financial-audit', [FinancialAdministrationController::class, 'auditHistory']);
            Route::post('/customers/{customer}/payments/{payment}/correction', [PaymentCorrectionController::class, 'store']);
        });
    });
});
