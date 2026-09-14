<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Compass\Controllers\FerryInternalNameserverController;
use Waterfront\Apps\API\Compass\Controllers\ManualMigrationController;

Route::prefix('manual-migration')
    ->name('manual-migration.')
    ->group(function (): void {
        Route::post('migrate/{customer:customer_number}', [ManualMigrationController::class, 'migrate'])->name(
            'migrate',
        );
        Route::post('validate/{customer:customer_number}', [ManualMigrationController::class, 'validate'])->name(
            'validate',
        );
        Route::get('migrate/server', [ManualMigrationController::class, 'listServers'])->name('listServers');
    });

Route::prefix('migrations/internal-nameservers')
    ->name('migrations.internal-nameservers.')
    ->group(function (): void {
        Route::get('list', [FerryInternalNameserverController::class, 'index'])->name('list');
        Route::post('', [FerryInternalNameserverController::class, 'store'])->name('store');
        Route::patch('{ferryInternalNameserver}', [FerryInternalNameserverController::class, 'update'])->name('update');
        Route::delete('{ferryInternalNameserver}', [FerryInternalNameserverController::class, 'destroy'])->name(
            'destroy',
        );
    });
