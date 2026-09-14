<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Waterfront\Controllers\ResellerHostingController;

Route::prefix('reseller-hosting')
    ->name('reseller-hosting.')
    ->group(
        function (): void {
            Route::get(
                '/',
                [ResellerHostingController::class, 'index'],
            )->name('index');

            Route::prefix('{resellerHostingDeployment:subscription_uuid}')->group(
                function (): void {
                    Route::get('/deployment', [ResellerHostingController::class, 'getDeployment'])->name('deployment');
                    Route::get('/', [ResellerHostingController::class, 'show'])->name('show');
                    Route::get('/sso', [ResellerHostingController::class, 'getSsoUrl'])->name('sso');
                    Route::patch('/reset-password', [ResellerHostingController::class, 'resetPassword'])->name(
                        'reset-password',
                    );
                    Route::get('/customers', [ResellerHostingController::class, 'getResellerSubCustomers'])->name(
                        'customers',
                    );
                    Route::post('/couple-domain', [ResellerHostingController::class, 'coupleExistingDomain'])->name(
                        'couple-domain',
                    );
                },
            );
        },
    );
