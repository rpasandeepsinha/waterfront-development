<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Middleware\RequireVerifiedCustomer;
use Waterfront\Apps\API\Waterfront\Controllers\DomainContactController;

Route::prefix('domain-contact')->name('domain-contact.')->group(
    function (): void {
        Route::get('contacts/{domainContact}/available-domains', [DomainContactController::class, 'availableDomains'])->name('contacts.available-domains');
        Route::patch('contacts/{contact}/default', [DomainContactController::class, 'setDefault'])->name('contacts.set_default');
        Route::post('contacts/{contact}/link', [DomainContactController::class, 'link'])->name('contacts.link');

        Route::apiResource('contacts', DomainContactController::class)->only(['show', 'store', 'destroy']);
    }
);

Route::prefix('domain-contact')->name('domain-contact.')->withoutMiddleware(RequireVerifiedCustomer::class)->group(
    function (): void {
        Route::apiResource('contacts', DomainContactController::class)->only(['index']);
    }
);
