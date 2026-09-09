<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Atlantis\Controllers\Microsoft365Controller;
use Waterfront\Apps\API\Middleware\RequireVerifiedCustomer;

Route::prefix('microsoft365')->name('m365.')->withoutMiddleware(RequireVerifiedCustomer::class)->group(
    function (): void {
        Route::post('/tenant-check', [Microsoft365Controller::class, 'tenantCheck'])->name('tenant-check');
    }
);
