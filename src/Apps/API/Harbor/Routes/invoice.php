<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Harbor\Controllers\InvoiceController;
use Waterfront\Apps\API\Kernel;

Route::prefix('harbor/api/v1/invoice')
    ->as('harbor.invoice.')
    ->middleware(Kernel::MIDDLEWARE_GROUP_SYSTEM_AUTH_WEBHOOK)
    ->group(
        function (): void {
            Route::post('/credit', [InvoiceController::class, 'credit'])->name('credit');
        },
    );
