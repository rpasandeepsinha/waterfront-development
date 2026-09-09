<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Support\Http\Controllers\Api\V1\PaymentController;

Route::get('payment/redirect/{order}', [PaymentController::class, 'redirect'])->name('payment.redirect');
