<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Compass\Controllers\SearchController;

Route::prefix('search')->name('search.')->group(function (): void {
    Route::get('/customers', [SearchController::class, 'customerSearch'])->name('customers');
    Route::get('/domains', [SearchController::class, 'domainSearch'])->name('domains');
    Route::get('/products', [SearchController::class, 'productSearch'])->name('products');
    Route::get('/migration-references', [SearchController::class, 'searchMigrationReference'])->name('migration-references');
});
