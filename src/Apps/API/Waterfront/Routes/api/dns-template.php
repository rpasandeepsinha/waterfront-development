<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Waterfront\Controllers\DnsTemplateController;

Route::prefix('dns')
    ->name('dns.')
    ->group(
        function (): void {
            Route::get('templates/{template}/available_domains', [
                DnsTemplateController::class,
                'templateDomains',
            ])->name('templates.available_domains');
            Route::post('templates/{template}/link', [DnsTemplateController::class, 'linkDomains'])->name(
                'templates.link',
            );
            Route::post('templates/{template}/unlink', [DnsTemplateController::class, 'unlinkDomain'])->name(
                'templates.unlink',
            );
            Route::post('templates/{template}/record', [DnsTemplateController::class, 'storeRecord'])->name(
                'templates.record',
            );
            Route::apiResource('/templates', DnsTemplateController::class)->only(['index', 'show', 'store', 'update']);
        },
    );
