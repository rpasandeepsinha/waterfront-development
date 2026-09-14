<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Atlantis\Controllers\DomainNameController;

Route::prefix('domain-name')
    ->name('domain-name')
    ->group(
        function (): void {
            Route::post('request-premium-price', [DomainNameController::class, 'requestPremiumDomainPrice'])->name(
                'request-premium-price',
            );
        },
    );
