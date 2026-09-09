<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Compass\Controllers\DeploymentController;
use Waterfront\Apps\API\Compass\Controllers\HostingController;
use Waterfront\Apps\API\Compass\Controllers\HostingDeploymentController;

Route::prefix('hosting/')->name('hosting.')->group(function (): void {
    Route::prefix('{subscription:uuid}/')->group(function (): void {
        Route::get('sso', [DeploymentController::class, 'getSsoUrl'])->name('sso');
        Route::put('downgrade/retry', [HostingController::class, 'retryDowngrade'])->name('retryDowngrade');
        Route::post('retry-hosting', [HostingController::class, 'retryHosting'])->name('retry.hosting');
    });
    Route::prefix('{hostingDeployment:subscription_uuid}/')->group(function (): void {
        Route::put('update-provider/{provider}', [HostingDeploymentController::class, 'updateProvider'])->name('update-provider');
    });
    Route::get('servers', [HostingController::class, 'servers'])->name('servers');
});
