<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Compass\Controllers\CustomersController;
use Waterfront\Apps\API\Compass\Controllers\ProductTransferController;
use Waterfront\Apps\API\Compass\Controllers\RetentionToolkitController;

Route::prefix('customers')
    ->name('customers.')
    ->group(function (): void {
        Route::get('/', [CustomersController::class, 'list'])->name('list');
        Route::post('/', [CustomersController::class, 'store'])->name('store');

        Route::prefix('{customer:customer_number}')->group(function (): void {
            Route::get('/', [CustomersController::class, 'show'])->name('show');
            Route::get('subscriptions/list', [CustomersController::class, 'listSubscriptions'])->name(
                'index.subscriptions',
            );
            Route::get('one-time-services/list', [CustomersController::class, 'listOneTimeServices'])->name(
                'one-time-services.list',
            );
            Route::get('audit-logs', [CustomersController::class, 'auditLogs'])->name('audit-logs');
            Route::get('orders/list', [CustomersController::class, 'listOrders'])->name('orders.list');
            Route::get('invoice-lines/unprocessed', [CustomersController::class, 'listUnprocessedInvoiceLines'])->name(
                'invoice-lines.unprocessed',
            );
            Route::post('invoice-lines/propagate', [CustomersController::class, 'propagateInvoiceLinesToHarbor'])->name(
                'invoice-lines.propagate',
            );
            Route::get('product-transfers/list', [ProductTransferController::class, 'listProductTransfers']);
            Route::post('anonymize', [CustomersController::class, 'anonymizeCustomer'])->name('anonymize');
            Route::get('wallet', [CustomersController::class, 'showWallet'])->name('wallet.show');
            Route::get('information/migration', [CustomersController::class, 'getMigrationInformation']);
            Route::post('request-bu-invoices', [CustomersController::class, 'requestBuInvoices'])->name(
                'requestBuInvoices',
            );
            Route::post('create-mandate', [CustomersController::class, 'createMandate'])->name('create.mandate');
            Route::post('mark-as-abusive', [CustomersController::class, 'markAsAbusiveCustomer'])->name(
                'mark.as.abuse',
            );
            Route::post('refresh-vat-rate', [CustomersController::class, 'refreshVatRate'])->name('refresh.vat.rate');
            Route::get('volume-discounts/available', [
                CustomersController::class,
                'listAvailableVolumeDiscounts',
            ])->name('volume-discounts.available');
            Route::post('add-volume-discount', [CustomersController::class, 'addVolumeDiscount'])->name(
                'add.volume.discount',
            );
            Route::post('enable-invoicing', [CustomersController::class, 'enableInvoicing'])->name('enable.invoicing');
            Route::put('update', [CustomersController::class, 'update'])->name('update');

            Route::prefix('notes')
                ->name('notes.')
                ->group(function (): void {
                    Route::get('list', [CustomersController::class, 'listNotes'])->name('list');
                    Route::post('create', [CustomersController::class, 'createNote'])->name('create');
                });

            Route::prefix('contacts')
                ->name('contacts.')
                ->group(function (): void {
                    Route::get('list', [CustomersController::class, 'listContacts'])->name('list');
                    Route::put('{customerContact:uuid}/update', [CustomersController::class, 'updateContact'])->name(
                        'update',
                    );
                });

            Route::prefix('retention-toolkit')
                ->name('retention-toolkit.')
                ->group(
                    function (): void {
                        Route::post('/calculate', [RetentionToolkitController::class, 'calculate'])->name('calculate');
                        Route::post('/apply', [RetentionToolkitController::class, 'apply'])->name('apply');
                    },
                );
        });
    });
