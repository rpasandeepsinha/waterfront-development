<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Waterfront\Controllers\NameServerController;

Route::prefix('domain/{subscription:uuid}/nameservers')->name('nameservers.')->group(
    function (): void {
        Route::get('/', [NameServerController::class, 'show'])->name('show');
        Route::put('/', [NameServerController::class, 'update'])->name('update');
        Route::post('reset', [NameServerController::class, 'reset'])->name('reset');
    }
);
