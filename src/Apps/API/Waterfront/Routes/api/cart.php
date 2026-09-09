<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Middleware\RequireVerifiedCustomer;
use Waterfront\Apps\API\Waterfront\Controllers\CartController;

Route::prefix('cart')->withoutMiddleware(RequireVerifiedCustomer::class)->name('cart.')->group(
    function (): void {
        Route::post('check', [CartController::class, 'calculateCart'])->name('calculate');
    }
);
