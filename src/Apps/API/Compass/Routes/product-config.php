<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Compass\Controllers\ProductDiscountController;
use Waterfront\Apps\API\Compass\Controllers\ProductGroupController;
use Waterfront\Apps\API\Compass\Controllers\ProductSpecController;
use Waterfront\Apps\API\Compass\Controllers\VoucherController;

Route::prefix('product-config')
    ->name('product-config.')
    ->group(function (): void {
        Route::prefix('product-specs')
            ->name('product-specs.')
            ->group(function (): void {
                Route::get('list', [ProductSpecController::class, 'listProductSpecs'])->name('list');
                Route::get('unique', [ProductSpecController::class, 'getAllUniqueProductSpecs'])->name('unique');
                Route::get('{productSpec:id}', [ProductSpecController::class, 'showProductSpec'])->name('show');
            });

        Route::prefix('product-discounts')
            ->name('product-discount.')
            ->group(function (): void {
                Route::get('list', [ProductDiscountController::class, 'list'])->name('list');
                Route::post('create', [ProductDiscountController::class, 'create'])->name('create');
                Route::get('{productDiscount}', [ProductDiscountController::class, 'show'])->name('show');
                Route::put('{productDiscount}/prices', [ProductDiscountController::class, 'updatePrices'])->name(
                    'prices.update',
                );
            });

        Route::prefix('product-groups')
            ->name('product-group.')
            ->group(function (): void {
                Route::get('list', [ProductGroupController::class, 'list'])->name('list');

                Route::get('{productGroup:uuid}', [ProductGroupController::class, 'show'])->name('show');
            });

        Route::prefix('vouchers')
            ->name('voucher.')
            ->group(function (): void {
                Route::get('/list', [VoucherController::class, 'listVouchers'])->name('list');
                Route::post('store', [VoucherController::class, 'storeVoucher'])->name('store');

                Route::prefix('{voucher:uuid}')->group(function (): void {
                    Route::get('', [VoucherController::class, 'showVoucher'])->name('show');
                    Route::put('update', [VoucherController::class, 'updateVoucher'])->name('update');
                });
            });
    });
