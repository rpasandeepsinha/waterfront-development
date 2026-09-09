<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Atlantis\Controllers\ProductController;
use Waterfront\Apps\API\Middleware\RequireVerifiedCustomer;

Route::get('/product', [ProductController::class, 'index'])
    ->name('product.index')
    ->withoutMiddleware(RequireVerifiedCustomer::class);
