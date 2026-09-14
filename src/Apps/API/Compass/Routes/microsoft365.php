<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Compass\Controllers\Microsoft365Controller;

Route::prefix('microsoft365/customer-info/{microsoft365CustomerInfo}')
    ->name('microsoft365.customer-info.')
    ->group(function (): void {
        Route::get('/', [Microsoft365Controller::class, 'tenantDetail'])->name('detail');
        Route::post('retry-create-kpn-customer', [Microsoft365Controller::class, 'retryCreateKpnCustomer'])->name(
            'retry-create-kpn-customer',
        );
    });

Route::prefix('microsoft365/deployment/{microsoft365Deployment}')
    ->name('microsoft365.deployment.')
    ->group(function (): void {
        Route::get('/', [Microsoft365Controller::class, 'deploymentDetail'])->name('detail');
        Route::post('retry-create-order', [Microsoft365Controller::class, 'retryCreateOrder'])->name(
            'retry-create-order',
        );
    });

Route::prefix('microsoft365/customer/{customer:customer_number}')
    ->name('microsoft365.customer.')
    ->group(function (): void {
        Route::get('overview', [Microsoft365Controller::class, 'customerOverview'])->name('overview');
    });

Route::prefix('microsoft365/subscription/{subscription:uuid}')
    ->name('microsoft365.subscription.')
    ->group(function (): void {
        Route::get('deployment', [Microsoft365Controller::class, 'subscriptionDeployment'])->name('deployment');
    });
