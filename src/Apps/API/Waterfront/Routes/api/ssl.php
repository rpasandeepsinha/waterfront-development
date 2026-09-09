<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Waterfront\Controllers\SslController;

Route::prefix('ssl')->name('ssl.')->group(
    function (): void {
        Route::get('download', [SslController::class, 'download'])->name('download');

        Route::prefix('{sslDeployment:subscription_uuid}')->group(
            function (): void {
                Route::get('deployment', [SslController::class, 'getDeployment'])->name('deployment');
                Route::post('retry-dcv', [SslController::class, 'retryDcv'])->name('retry-dcv');
            }
        );
    }
);
