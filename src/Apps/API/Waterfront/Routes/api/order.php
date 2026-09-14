<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Middleware\RequireVerifiedCustomer;
use Waterfront\Apps\API\Waterfront\Controllers\OrderController;

Route::prefix('order')
    ->withoutMiddleware(RequireVerifiedCustomer::class)
    ->name('order.')
    ->group(
        function (): void {
            Route::get('{order}', [OrderController::class, 'status'])->name('status');
            Route::post('', [OrderController::class, 'order'])->name('order');
            Route::post('{order}/retry', [OrderController::class, 'retry'])->name('retry');
        },
    );
