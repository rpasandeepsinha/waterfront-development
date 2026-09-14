<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Waterfront\Controllers\RedirectController;

Route::prefix('redirects/{subscription:uuid}')
    ->name('redirects.')
    ->group(
        function (): void {
            Route::get('/deployment', [RedirectController::class, 'getDeployment'])->name('getDeployment');
            Route::post('/', [RedirectController::class, 'store'])->name('store');
            Route::match(['put', 'patch'], '/', [RedirectController::class, 'update'])->name('update');
            Route::delete('/', [RedirectController::class, 'destroy'])->name('destroy');
        },
    );
