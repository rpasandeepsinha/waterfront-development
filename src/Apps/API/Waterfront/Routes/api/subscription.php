<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Waterfront\Controllers\CancellationFlowController;
use Waterfront\Apps\API\Waterfront\Controllers\SubscriptionController;

Route::prefix('subscriptions/')->name('subscriptions.')->group(
    function (): void {
        Route::prefix('{subscription:uuid}')->group(function (): void {
            Route::post('/cancel/revert', [SubscriptionController::class, 'revertCancel'])->name('cancel.revert');
            Route::get('/get-potential-upgrades', [SubscriptionController::class, 'getPotentialUpgrades'])->name('potential.upgrades');
            Route::get('/get-potential-downgrades', [SubscriptionController::class, 'getPotentialDowngrades'])->name('potential.downgrades');
        });

        Route::post('cancel', [SubscriptionController::class, 'cancel'])->name('cancel');
        Route::post('cancel/child-subscriptions', [SubscriptionController::class, 'cancelChildSubscription'])->name('cancel.child-subscriptions');
        Route::get('/overview', [SubscriptionController::class, 'indexOverview'])->name('index.overview');
        Route::get('/service-plus/eligible', [SubscriptionController::class, 'getSubscriptionsEligibleForServicePlus'])->name('service-plus.eligible');
        Route::prefix('/{subscription:uuid}/')->group(
            function (): void {
                Route::get('show', [SubscriptionController::class, 'show'])->name('show');
                Route::get('domain-related', [SubscriptionController::class, 'getDomainAndCustomerRelationSubscriptions'])->name('domain-related');
            }
        );

        Route::prefix('cancellation-flow')->group(function (): void {
            Route::post('create', [CancellationFlowController::class, 'create'])->name('create');
            Route::post('/{cancellationFlow}/process-step', [CancellationFlowController::class, 'processStep']);
        });
    }
);

Route::resource('subscriptions', SubscriptionController::class)->only('index');
