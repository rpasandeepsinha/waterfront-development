<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Compass\Controllers\ShopConfigController;

Route::prefix('shop-config')
    ->name('shop-config.')
    ->group(function (): void {
        Route::get('/', [ShopConfigController::class, 'show'])->name('show');
        Route::put('/', [ShopConfigController::class, 'update'])->name('update');
    });
