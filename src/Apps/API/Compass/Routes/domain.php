<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Compass\Controllers\DomainBusinessUnitController;
use Waterfront\Apps\API\Compass\Controllers\DomainController;
use Waterfront\Apps\API\Compass\Controllers\DomainDeploymentController;
use Waterfront\Apps\API\Compass\Controllers\DomainProviderController;
use Waterfront\Apps\API\Compass\Controllers\HostingDeploymentController;
use Waterfront\Apps\API\Compass\Controllers\SslDeploymentController;

Route::get('domain/business-units', [DomainBusinessUnitController::class, 'index'])->name('domain.business-units');

Route::prefix('domain/{domain}')->name('domain.')->group(function (): void {
    Route::get('/contact', [DomainController::class, 'contact'])->name('contact');
    Route::get('/nameservers', [DomainController::class, 'nameservers'])->name('nameservers');
    Route::get('/dns-zone-from-source', [DomainController::class, 'fetchDnsZoneFromSource'])->name('dns.from-source');
    Route::get('/processes', [DomainController::class, 'processes'])->name('processes');
    Route::get('/revisions', [DomainController::class, 'revisions'])->name('revisions');
    Route::get('/audit-logs', [DomainController::class, 'auditLogs'])->name('audit-logs');
    Route::patch('/provider', [DomainProviderController::class, 'update'])->name('provider.update');
    Route::patch('/business-unit', [DomainBusinessUnitController::class, 'update'])->name('business-unit.update');
    Route::get('/deployment', [DomainDeploymentController::class, 'deployment'])->name('deployment');
    Route::get('/hosting', [HostingDeploymentController::class, 'deployment'])->name('hosting');
    Route::get('/ssl', [SslDeploymentController::class, 'deployment'])->name('ssl');

    Route::prefix('dns')->name('dns.')->group(function (): void {
        Route::get('', [DomainController::class, 'dns'])->name('index');
        Route::post('/redeploy', [DomainController::class, 'redeployDns'])->name('redeploy');
    });
});
