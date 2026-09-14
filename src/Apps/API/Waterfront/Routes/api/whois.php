<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Waterfront\Controllers\WhoisController;

Route::prefix('domain-name/{domain}/whois')
    ->name('whois.')
    ->group(
        function (): void {
            Route::get('/', [WhoisController::class, 'index'])->name('index');
            Route::put('/', [WhoisController::class, 'update'])->name('update');
            Route::post('/enable-private-whois', [WhoisController::class, 'enablePrivateWhois'])->name(
                'enable-private-whois',
            );
            Route::post('/disable-private-whois', [WhoisController::class, 'disablePrivateWhois'])->name(
                'disable-private-whois',
            );
        },
    );
