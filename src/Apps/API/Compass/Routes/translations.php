<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Compass\Controllers\TranslationsController;

Route::prefix('translations')->name('translations.')->group(function (): void {
    Route::post('/dictionary/update', [TranslationsController::class, 'updateDictionary'])->name('dictionary.update');
    Route::prefix('{source}')->group(function (): void {
        Route::get('/', [TranslationsController::class, 'list'])->name('index');
        Route::patch('/', [TranslationsController::class, 'updateTranslation'])->name('update');
        Route::post('/', [TranslationsController::class, 'addTranslations'])->name('add');
    });
});
