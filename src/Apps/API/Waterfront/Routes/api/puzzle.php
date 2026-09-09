<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Waterfront\Controllers\ScheduledSupportCallController;

Route::prefix('scheduled-support-call')->name('puzzel.support-call.')->group(
    function (): void {
        Route::get('time-slots', [ScheduledSupportCallController::class, 'timeslots'])->name('time-slots');
        Route::post('/', [ScheduledSupportCallController::class, 'store'])->name('store');
    }
);
