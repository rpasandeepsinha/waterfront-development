<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Waterfront\Controllers\DomainNameController;

Route::prefix('domain-name')->name('domain-name.')->group(
    function (): void {
        Route::get('/{domainDeployment:subscription_uuid}/transfer-code', [DomainNameController::class, 'transferCode'])->name('transfer-code');
        Route::post('couple/{subscription:uuid}', [DomainNameController::class, 'couple'])->name('couple');
        Route::post('decouple/{subscription:uuid}', [DomainNameController::class, 'decouple'])->name('decouple');
        Route::prefix('{domain}')->group(
            function (): void {
                Route::get('dnssec', [DomainNameController::class, 'indexDnssec'])->name('dnssec');
                Route::post('enable-dnssec', [DomainNameController::class, 'enableDnssec'])->name('enable-dnssec');
                Route::post('disable-dnssec', [DomainNameController::class, 'disableDnssec'])->name('disable-dnssec');

                Route::post('retry-provisioning', [DomainNameController::class, 'retryProvisioning'])->name('retry-provisioning');

                Route::get('manual-dnssec-available', [DomainNameController::class, 'manualDnssecAvailable'])->name('manual-dnssec-available');
            }
        );
    }
);
