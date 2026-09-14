<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Kernel;
use Waterfront\Domain\Microsoft365\Http\Controllers\WebhookController;

Route::middleware(Kernel::MIDDLEWARE_GROUP_NO_AUTH_WEBHOOK)
    ->namespace('Waterfront\Domain\Microsoft365\Http\Controllers\Api\V1')
    ->as('microsoft365.webhook.')
    ->group(
        function (): void {
            Route::post('microsoft365/api/v1/webhook/kpn', [WebhookController::class, 'incomingCall']);
        },
    );
