<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Compass\Controllers\EmailController;

Route::prefix('email-template')
    ->name('email-template.')
    ->group(function (): void {
        Route::get('', [EmailController::class, 'listTemplates'])->name('list.templates');
        Route::post('', [EmailController::class, 'createTemplate'])->name('create.template');
        Route::put('/{template}', [EmailController::class, 'updateTemplate'])->name('update.template');
        Route::get('/{template}', [EmailController::class, 'showTemplate']);
    });
Route::get('email-history/{string:uuid}', [EmailController::class, 'indexEmailHistory'])->name('email-history');

Route::prefix('email-history')
    ->name('email-history.')
    ->group(function (): void {
        Route::get('/{uuid}', [EmailController::class, 'indexEmailHistory'])->name('email-history');

        Route::prefix('{emailHistory}')
            ->name('email-history.')
            ->group(function (): void {
                Route::get('show', [EmailController::class, 'showEmail'])->name('index');
                Route::post('', [EmailController::class, 'resendEmail'])->name('resend');
                Route::post('/status', [EmailController::class, 'fetchStatus'])->name('fetch-status');
            });
    });
