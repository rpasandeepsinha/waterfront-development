<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Middleware\RequireVerifiedCustomer;
use Waterfront\Apps\API\Waterfront\Controllers\CustomerContactController;
use Waterfront\Apps\API\Waterfront\Controllers\CustomersController;

Route::prefix('customers')
    ->name('customers.')
    ->group(
        function (): void {
            Route::patch('{customer:uuid}', [CustomersController::class, 'patch'])->name('patch');
            Route::post('/{customer:uuid}/validate', [CustomersController::class, 'validate'])->name('validate');
            Route::post('/confirm-data', [CustomersController::class, 'confirmData'])->name('confirm.data');
            Route::post('/direct-debit/enable', [CustomersController::class, 'enableDirectDebit'])->name(
                'direct-debit.enable',
            );

            Route::name('contacts.')
                ->prefix('{customer:uuid}/contacts')
                ->group(
                    function (): void {
                        Route::get('/', [CustomerContactController::class, 'index'])->name('index');
                        Route::post('/', [CustomerContactController::class, 'store'])->name('store');
                        Route::patch('/{customerContact}', [CustomerContactController::class, 'update'])->name(
                            'update',
                        );
                        Route::delete('/{customerContact}', [CustomerContactController::class, 'destroy'])->name(
                            'destroy',
                        );
                    },
                );
        },
    );

Route::get('customers/who-am-i', [CustomersController::class, 'whoAmI'])->name(
    'customers.who-am-i',
)->withoutMiddleware(RequireVerifiedCustomer::class);
