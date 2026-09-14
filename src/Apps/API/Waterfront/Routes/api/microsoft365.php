<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Waterfront\Controllers\Microsoft365Controller;

Route::prefix('microsoft365')->as('microsoft365.')->group(
    function (): void {
        Route::get('/', [Microsoft365Controller::class, 'microsoftInformation'])->name('microsoft-information');
        Route::post('/update-primary-domain', [Microsoft365Controller::class, 'updatePrimaryDomain'])->name(
            'microsoft-update-primary-domain',
        );
        Route::get('/mca-agreement', [Microsoft365Controller::class, 'getMicrosoftCustomerAgreementUrl'])->name(
            'microsoft-customer-agreement',
        );
        Route::post('/mca-agreement', [Microsoft365Controller::class, 'microsoftCustomerAgreementSigned'])->name(
            'microsoft-customer-agreement-signed',
        );
    },
);
