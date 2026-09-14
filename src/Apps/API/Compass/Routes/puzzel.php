<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Compass\Controllers\PuzzelBlockedDateController;

Route::prefix('puzzel')
    ->name('puzzel.')
    ->group(function (): void {
        Route::prefix('blocked-dates')
            ->name('blocked-dates.')
            ->group(function (): void {
                Route::get('/', [PuzzelBlockedDateController::class, 'list'])->name('list');
                Route::post('/', [PuzzelBlockedDateController::class, 'store'])->name('store');
                Route::delete('/', [PuzzelBlockedDateController::class, 'destroy'])->name('destroy');
            });
    });
