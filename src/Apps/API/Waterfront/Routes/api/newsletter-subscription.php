<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Middleware\RequireVerifiedCustomer;
use Waterfront\Apps\API\Waterfront\Controllers\NewsLetterController;

Route::prefix('newsletter-subscriptions')->name('newsletter-subscriptions.')->group(
    function (): void {
        Route::get('is-opted-in', [NewsLetterController::class, 'isOptedInMarketingEmails'])->name('is-opted-in');
        Route::post(
            'subscribe',
            [NewsLetterController::class, 'subscribe']
        )
            ->name('subscribe')
            ->withoutMiddleware(RequireVerifiedCustomer::class);
        Route::post('unsubscribe', [NewsLetterController::class, 'unsubscribe'])->name('unsubscribe');
    }
);

Route::get('newsletter-subscriptions/is-subscribed', [NewsLetterController::class, 'isSubscribed'])
    ->name('newsletter-subscriptions.is-subscribed')
    ->withoutMiddleware(RequireVerifiedCustomer::class);
