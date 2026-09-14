<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Atlantis\Controllers\CustomerActionController;
use Waterfront\Apps\API\Middleware\RequireVerifiedCustomer;

Route::prefix('customer-actions')
    ->name('customer-actions.')
    ->withoutMiddleware(RequireVerifiedCustomer::class)
    ->group(
        function (): void {
            Route::get('/{order:uuid}', [CustomerActionController::class, 'show'])->name('show');
        },
    );
