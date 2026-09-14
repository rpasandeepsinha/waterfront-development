<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Kernel;
use Waterfront\Apps\API\Waterfront\Controllers\HubspotController;
use Waterfront\Apps\Webhooks\Controllers\EmailController;
use Waterfront\Apps\Webhooks\Controllers\PaytController;

$partnerDomain = Config::get('app.url_partner_api');
$webhookDomain = Config::get('app.url_webhook');
assert(is_string($partnerDomain));
assert(is_string($webhookDomain));

Route::domain($partnerDomain)
    ->as('webhooks.')
    ->prefix('webhooks')
    ->middleware(Kernel::MIDDLEWARE_GROUP_SYSTEM_AUTH_WEBHOOK)
    ->group(function (): void {
        Route::prefix('email')->as('email.')->group(
            static function (): void {
                Route::post('/send', [EmailController::class, 'sendEmail'])->name('send');
            },
        );
    });

Route::domain($webhookDomain)
    ->as('webhooks.')
    ->prefix('webhooks')
    ->middleware(Kernel::MIDDLEWARE_GROUP_SYSTEM_AUTH_WEBHOOK)
    ->group(function (): void {
        Route::get('/get-customer-data', [HubspotController::class, 'getCustomerData'])->name(
            'hubspot.get-customer-data',
        );
        Route::post('/payt/{businessUnit}/webhook', [PaytController::class, 'handleEvent'])->name('payt.new-event');
    });
