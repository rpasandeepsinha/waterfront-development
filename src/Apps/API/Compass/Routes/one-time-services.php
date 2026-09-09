<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Compass\Controllers\OneTimeServiceController;

Route::prefix('one-time-services')->name('one-time-services.')->group(function (): void {
    Route::get('/{oneTimeService:uuid}', [OneTimeServiceController::class, 'show'])->name('show');
});
