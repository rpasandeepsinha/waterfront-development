<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Compass\Controllers\InfrastructureController;

Route::prefix('infrastructure')
    ->name('infrastructure.')
    ->group(function (): void {
        Route::get('servers/hosting/list', [InfrastructureController::class, 'listHostingServers']);
        Route::get('nameservers/list', [InfrastructureController::class, 'listNameservers']);
        Route::get('dns-templates/list', [InfrastructureController::class, 'listDnsTemplates']);
        Route::get('dns-templates/{dns_template}', [InfrastructureController::class, 'showDnsTemplate']);
        Route::post('servers/hosting/import', [InfrastructureController::class, 'importHostingServers'])->name(
            'servers.hosting.import',
        );
        Route::get('servers/hosting/{server}/packages', [InfrastructureController::class, 'listServerPackages'])->name(
            'servers.hosting.packages',
        );
        Route::get('servers/hosting/{server}/package', [InfrastructureController::class, 'showServerPackage'])->name(
            'servers.hosting.package',
        );
        Route::get('servers/hosting/{server}', [InfrastructureController::class, 'showHostingServer']);
        Route::prefix('servers/legacy-redirecting')
            ->name('servers.legacy-redirecting.')
            ->group(function (): void {
                Route::get('list', [InfrastructureController::class, 'listLegacyRedirectingServers'])->name('list');
                Route::post('store', [InfrastructureController::class, 'storeLegacyRedirectingServer'])->name('store');
                Route::get('{legacy_redirecting_server}', [
                    InfrastructureController::class,
                    'showLegacyRedirectingServer',
                ])->name('show');
                Route::put('{legacy_redirecting_server}/update', [
                    InfrastructureController::class,
                    'updateLegacyRedirectingServer',
                ])->name('update');
            });
        Route::post('servers/hosting', [InfrastructureController::class, 'storeHostingServer'])->name(
            'servers.hosting.store',
        );
        Route::put('servers/hosting/{server}', [InfrastructureController::class, 'updateHostingServer'])->name(
            'servers.hosting.update',
        );
        Route::post('servers/hosting/{server}/fetch-user', [InfrastructureController::class, 'fetchServerUser'])->name(
            'servers.hosting.fetch-user',
        );
        Route::get('{providerType}/getProviders', [InfrastructureController::class, 'getProviders'])->name('providers');
    });
