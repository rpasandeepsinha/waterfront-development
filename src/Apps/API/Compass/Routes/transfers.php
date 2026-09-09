<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Compass\Controllers\ProductTransferController;

Route::prefix('transfer')->group(function (): void {
    Route::post('/{productTransfer:uuid}', [ProductTransferController::class, 'retryProductTransfer'])->name('transfer.retry');
});
