<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Compass\Controllers\WalletController;

Route::prefix('wallets')->name('wallet.')->group(function (): void {
    Route::get('/', [WalletController::class, 'listWallets'])->name('list');
    Route::post('request-wallet-refund-csv', [WalletController::class, 'requestWalletRefundCsv'])->name('requestWalletRefundCsv');
});
