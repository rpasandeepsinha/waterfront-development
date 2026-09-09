<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Waterfront\Controllers\BackupController;

Route::prefix('backup/{subscription:uuid}')->name('backup.')->group(function (): void {
    Route::get('/sso', [BackupController::class, 'getSsoUrl'])->name('sso');
    Route::get('/usage', [BackupController::class, 'getUsage'])->name('usage');
});
