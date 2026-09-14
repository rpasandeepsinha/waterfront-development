<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Compass\Controllers\ProductsController;

Route::prefix('products')
    ->name('products.')
    ->group(function (): void {
        Route::get('/list', [ProductsController::class, 'list'])->name('list');
        Route::get('/all', [ProductsController::class, 'getAllProducts'])->name('all');
        Route::post('/create', [ProductsController::class, 'create'])->name('create');
        Route::post('/export-to-bucket', [ProductsController::class, 'exportProductsToBucket'])->name(
            'export-products-to-bucket',
        );

        Route::prefix('{product:uuid}')
            ->name('show.')
            ->group(function (): void {
                Route::put('/update', [ProductsController::class, 'update'])->name('update');
                Route::get('', [ProductsController::class, 'show'])->name('details');
                Route::get('prices', [ProductsController::class, 'showPublicProductPrices'])->name('prices');
                Route::get('specs/list', [ProductsController::class, 'showProductSpecs'])->name('specs.list');
                Route::get('allowed-changes', [ProductsController::class, 'allowedProductChanges'])->name(
                    'allowed-changes',
                );
                Route::get('/relevant', [ProductsController::class, 'showRelevantProducts'])->name('relevant-products');
                Route::get('/promotions/list', [ProductsController::class, 'productPromotionsList'])->name(
                    'product-promotions.list',
                );
            });
    });
