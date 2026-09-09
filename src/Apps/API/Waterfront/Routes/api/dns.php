<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Waterfront\Controllers\DnsController;
use Waterfront\Apps\API\Waterfront\Controllers\DnsRecordChangesController;
use Waterfront\Apps\API\Waterfront\Controllers\DnsZoneController;

Route::prefix('domain/{domain}')->group(
    function (): void {
        Route::apiResource('dns', DnsController::class)->except('show')->parameter('dns', 'dns');
        // Zones
        Route::apiResource('dns-zone', DnsZoneController::class)->only('store');
    }
);

Route::prefix('dns/{subscription:uuid}')->group(
    function (): void {
        Route::get('record-changes', [DnsRecordChangesController::class, 'index'])->name('dns.record-changes');
    }
);
