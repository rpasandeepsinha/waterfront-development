<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Compass\Controllers\SslDeploymentController;

Route::prefix('ssl')->name('ssl.')->group(function (): void {
    Route::prefix('{sslDeployment:subscription_uuid}')->group(function (): void {
        Route::get('download', [SslDeploymentController::class, 'download'])->name('download');
        Route::post('update-request-status', [SslDeploymentController::class, 'updateSslRequestStatus'])->name('ssl.update-request-status');
        Route::post('retry', [SslDeploymentController::class, 'retrySslDeployment'])->name('retry');
        Route::post('sync-certificate-from-rtr', [SslDeploymentController::class, 'syncCertificateFromRtr'])->name('ssl.sync-certificate-from-rtr');
        Route::post('set-dns-verify-record', [SslDeploymentController::class, 'setSslDnsVerifyRecord'])->name('set-dns-verify-record');
        Route::put('/update-provider/{provider}', [SslDeploymentController::class, 'updateProvider'])->name('update-provider');
    });
});
