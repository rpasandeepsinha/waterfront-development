<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Compass\Controllers\DeploymentController;
use Waterfront\Apps\API\Compass\Controllers\ProvisionRetryController;

Route::prefix('deployments/')
    ->name('deployments.')
    ->group(function (): void {
        Route::get('requests/failed', [DeploymentController::class, 'FailedProvisioningResults']);
        Route::post('requests/retry', [ProvisionRetryController::class, 'retry'])->name('requests.retry');
        Route::prefix('{subscription:uuid}')
            ->name('show.')
            ->group(function (): void {
                Route::put('/retry/update', [DeploymentController::class, 'updateRetry'])->name('updateRetry');
            });
    });

Route::prefix('subscriptions/deployments/')
    ->name('subscriptions.')
    ->group(function (): void {
        Route::prefix('{hostingDeployment:subscription_uuid}')
            ->name('deployments.')
            ->group(function (): void {
                Route::put('/update', [DeploymentController::class, 'updateDeployment'])->name('update');
            });
    });
