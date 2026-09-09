<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Waterfront\Controllers\CustomersController;

/**
 * @see \Waterfront\Apps\API\Partners\Providers\RouteServiceProvider
 */
Route::post('/customers/register', [CustomersController::class, 'register'])->name('customers.register');
