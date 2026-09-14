<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Waterfront\Controllers\DomainNameController;

Route::prefix('domain')
    ->name('domain.')
    ->group(
        function (): void {
            Route::prefix('{domainDeployment:subscription_uuid}')->group(
                function (): void {
                    Route::get('deployment', [DomainNameController::class, 'getDeployment'])->name('deployment');
                },
            );
        },
    );
