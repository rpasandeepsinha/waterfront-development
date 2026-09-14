<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Middleware\RequireVerifiedCustomer;
use Waterfront\Apps\API\Waterfront\Controllers\PaymentMandateController;

Route::get('/payment/mandate', [PaymentMandateController::class, 'hasMandate'])->name(
    'payment.mandate.has_mandate',
)->withoutMiddleware(RequireVerifiedCustomer::class);
