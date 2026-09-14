<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Compass\Controllers\DnsRecordChangeController;
use Waterfront\Apps\API\Compass\Controllers\DnsZoneController;

Route::prefix('dns/{domain}')
    ->name('dns.')
    ->group(function (): void {
        Route::get('/dns-record-change', [DnsRecordChangeController::class, 'index'])->name('dns_record_change');
    });

Route::prefix('dns-zone/{domain}')
    ->name('dns-zone.')
    ->group(function (): void {
        Route::post('/', [DnsZoneController::class, 'store'])->name('store');
        Route::delete('/', [DnsZoneController::class, 'delete'])->name('delete');
    });
