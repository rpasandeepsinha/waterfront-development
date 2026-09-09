<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Waterfront\Controllers\SitebuilderController;

Route::prefix('domain/{hostingDeployment:subscription_uuid}/sitebuilder')->name('sitebuilder.')->group(
    function (): void {
        Route::get('sso', [SitebuilderController::class, 'getSsoUrl'])->name('sso');
    }
);
