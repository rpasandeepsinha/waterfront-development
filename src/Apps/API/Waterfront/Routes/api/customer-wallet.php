<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Waterfront\Controllers\CustomerWalletController;

Route::prefix('customer-wallet/{customer}')->as('customer-wallet.')->group(
    function (): void {
        Route::get('/', [CustomerWalletController::class, 'show'])->name('show');
        Route::post('/request-refund', [CustomerWalletController::class, 'requestRefund'])->name('request-refund');
    },
);
