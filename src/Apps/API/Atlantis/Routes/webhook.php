<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Support\Http\Controllers\Api\V1\PaymentController;

Route::post('payment/webhook', [PaymentController::class, 'webhook'])->name('payment.webhook');
