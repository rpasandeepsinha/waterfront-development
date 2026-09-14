<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Compass\Controllers\MigrationStateController;

Route::prefix('migration-state')
    ->name('migration-state.')
    ->group(function (): void {
        Route::get('list', [MigrationStateController::class, 'list'])->name('list');
        Route::get('{subscription:uuid}', [MigrationStateController::class, 'show'])->name('show');
    });
