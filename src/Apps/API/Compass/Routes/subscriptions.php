<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Compass\Controllers\CancelSubscriptionsController;
use Waterfront\Apps\API\Compass\Controllers\OneTimeServiceController;
use Waterfront\Apps\API\Compass\Controllers\ProductTransferController;
use Waterfront\Apps\API\Compass\Controllers\ResellerHostingDeploymentController;
use Waterfront\Apps\API\Compass\Controllers\SubscriptionsController;

Route::prefix('subscriptions/')->name('subscriptions.')->group(function (): void {
    Route::get('list', [SubscriptionsController::class, 'listSubscriptions'])->name('index.subscriptions');
    Route::get('bydomain/{domain}', [SubscriptionsController::class, 'listSubscriptionsForDomain'])->name('list.for.domain');
    Route::post('cancel-and-credit', [CancelSubscriptionsController::class, 'cancelAndCredit'])->name('cancel-and-credit');
    Route::post('cancel-and-credit/check', [CancelSubscriptionsController::class, 'check'])->name('cancel-and-credit.check');
    Route::get('{identifier}', [SubscriptionsController::class, 'show'])->name('subscription.show');
    Route::delete('mutations/{subscriptionMutation}', [SubscriptionsController::class, 'deleteMutation'])->name('delete.mutation');

    Route::prefix('{subscription}')->name('subscription.')->group(function (): void {
        Route::get('audit-logs', [SubscriptionsController::class, 'auditLogs'])->name('audit-logs');
        Route::get('mutations', [SubscriptionsController::class, 'listMutations'])->name('list.mutations');
        Route::get('resellerHostingDeployment', [ResellerHostingDeploymentController::class, 'deployment'])->name('reseller-hosting-deployment');
        Route::get('changes', [SubscriptionsController::class, 'listChanges'])->name('list.changes');
        Route::get('notes', [SubscriptionsController::class, 'listNotes'])->name('notes');
        Route::get('product-transfers', [ProductTransferController::class, 'productTransfers'])->name('product.transfers');
        Route::get('migration', [SubscriptionsController::class, 'migration'])->name('migration');
        Route::get('provisioning-requests', [SubscriptionsController::class, 'provisioningRequests'])->name('provisioning-requests');
        Route::put('update-contract', [SubscriptionsController::class, 'updateContractPeriodWithDiscount'])->name('update.contract');
        Route::post('cancel/revert', [SubscriptionsController::class, 'revertCancel'])->name('cancel.revert');
        Route::post('suspend', [SubscriptionsController::class, 'suspendSubscription'])->name('suspend');
        Route::post('unsuspend', [SubscriptionsController::class, 'unSuspendSubscription'])->name('unsuspend');
        Route::put('update', [SubscriptionsController::class, 'update'])->name('update');
        Route::post('assign-employee', [SubscriptionsController::class, 'assignEmployee'])->name('assign-employee');
        Route::post('assign-category', [SubscriptionsController::class, 'assignCategory'])->name('assign-category');
        Route::get('invoice/prefill', [SubscriptionsController::class, 'invoicePrefill'])->name('invoice.prefill');
        Route::post('invoice', [SubscriptionsController::class, 'createInvoice'])->name('invoice.create');
        Route::post('one-time-services', [OneTimeServiceController::class, 'store'])->name('one-time-services.store');
        Route::post('one-time-services/preview', [OneTimeServiceController::class, 'preview'])->name('one-time-services.preview');
    });

    Route::prefix('{subscription:uuid}')->name('subscription.')->group(function (): void {
        Route::post('add-domain-to-spam-experts', [SubscriptionsController::class, 'addDomainToSpamExperts'])->name('add.domain.to.spam.experts');
    });
});
