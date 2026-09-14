<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Compass\Controllers\OrderController;

Route::prefix('orders')
    ->name('orders.')
    ->group(function (): void {
        Route::get('{order}/show', [OrderController::class, 'show'])->name('show');
        Route::post('{order}/retry', [OrderController::class, 'retryOrder'])->name('retry');
        Route::post('line-items/{lineItem}/process-line-item', [OrderController::class, 'processLineItem'])->name(
            'line-items.process.line.item',
        );
    });
