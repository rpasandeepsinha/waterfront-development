<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Compass\Controllers\OneTimeServiceController;

Route::prefix('one-time-services/{oneTimeService:uuid}')
    ->name('one-time-services.')
    ->group(function (): void {
        Route::get('', [OneTimeServiceController::class, 'show'])->name('show');
        Route::post('/invoice', [OneTimeServiceController::class, 'invoice'])->name('invoice');
    });
