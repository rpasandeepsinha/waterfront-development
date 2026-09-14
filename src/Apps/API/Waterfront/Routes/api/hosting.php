<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Waterfront\Controllers\HostingController;

Route::prefix('hosting')
    ->name('hosting.')
    ->group(
        function (): void {
            Route::prefix('{subscription:uuid}')->group(
                function (): void {
                    Route::get('customer-config', [HostingController::class, 'customerConfig'])->name(
                        'customer-config',
                    );
                    Route::put('modify-customer', [HostingController::class, 'modifyCustomer'])->name(
                        'modify-customer',
                    );
                    Route::get('sso', [HostingController::class, 'getSsoUrl'])->name('sso');
                    Route::get('reset', [HostingController::class, 'resetPassword'])->name('reset_password');
                    Route::get('stats', [HostingController::class, 'getUserStats'])->name('get_user_stats');
                    Route::get('wp-toolkit-sso', [HostingController::class, 'wpToolkitSSO'])->name('wp-toolkit-sso');
                },
            );

            Route::prefix('{hostingDeployment:subscription_uuid}')->group(
                function (): void {
                    Route::get('domain-slot', [HostingController::class, 'getDomainOccupation'])->name('domain-slot');
                    Route::get('deployment', [HostingController::class, 'getDeployment'])->name('deployment');
                    Route::get('list-hosting-domains', [HostingController::class, 'listHostingDomains'])->name(
                        'list-hosting-domains',
                    );
                    Route::get('dkim-record/{domain}', [HostingController::class, 'dkimRecord'])->name('dkim-record');
                    Route::post('enable-dkim', [HostingController::class, 'enableDkim'])->name('enable-dkim');
                },
            );
        },
    );

//TODO:: Refactor these routes to be consistent with the other ones. WATER-4925
Route::prefix('domain/{domain}/hosting')
    ->name('hosting.')
    ->group(
        function (): void {
            Route::post('couple-hosting', [HostingController::class, 'addDomainToExistingHosting'])->name(
                'couple-hosting',
            );
            Route::get('get-coupled', [HostingController::class, 'getCoupledHostingByDomain'])->name('get-coupled');
            Route::post('decouple', [HostingController::class, 'decoupleHostingByDomain'])->name('decouple');
        },
    );
