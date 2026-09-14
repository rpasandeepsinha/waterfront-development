<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Waterfront\Controllers\LabelController;

Route::prefix('labels')
    ->name('labels.')
    ->group(
        function (): void {
            Route::get('/', [LabelController::class, 'getLabels'])->name('show');
            Route::post('/', [LabelController::class, 'createLabels'])->name('create');
            Route::delete('/', [LabelController::class, 'deleteLabels'])->name('delete');

            Route::prefix('{label}')
                ->name('labels.')
                ->group(
                    function (): void {
                        Route::post('link', [LabelController::class, 'attachSubscriptions'])->name(
                            'attach-subscriptions',
                        );
                        Route::post('unlink', [LabelController::class, 'detachSubscriptions'])->name(
                            'detach-subscriptions',
                        );
                    },
                );
        },
    );
