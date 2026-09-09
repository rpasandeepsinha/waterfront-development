<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Middleware\RequireVerifiedCustomer;
use Waterfront\Apps\API\Waterfront\Controllers\OrderController;

// Unregistered BUT has session routes
Route::prefix('')->withoutMiddleware([RequireVerifiedCustomer::class])->group(
    function (): void {
        Route::post('cart', [OrderController::class, 'order'])->name('cart.store');
    }
);
