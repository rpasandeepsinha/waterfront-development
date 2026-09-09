<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Waterfront\Controllers\TransferController;

Route::prefix('transfers')->name('transfers.')->group(
    function (): void {
        Route::get('/', [TransferController::class, 'index'])->name('index');
        Route::get('/{transfer}', [TransferController::class, 'show'])->name('show');
        Route::post('{transfer}/accept', [TransferController::class, 'accept'])->name('accept');
        Route::post('/initiate', [TransferController::class, 'store'])->name('initiate');
        Route::post('/{transfer}/cancel', [TransferController::class, 'cancel'])->name('cancel');
        Route::post('/{transfer}/reject', [TransferController::class, 'reject'])->name('reject');
    }
);
