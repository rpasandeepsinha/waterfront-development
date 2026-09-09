<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Middleware\RequireVerifiedCustomer;
use Waterfront\Apps\API\Waterfront\Controllers\CloudStack\SshKeyController;
use Waterfront\Apps\API\Waterfront\Controllers\CloudStack\VirtualMachineController;

Route::prefix('cloudstack')->as('cloudstack.')->group(
    function (): void {
        // VirtualMachine
        Route::prefix('virtual-machine')->name('virtual-machine.')->group(function (): void {
            Route::get('/', [VirtualMachineController::class, 'index'])->name('index');
            Route::get('{virtualMachineDeployment:subscription_uuid}/getDeployment', [VirtualMachineController::class, 'getDeployment'])->name('deployment');

            Route::prefix('{subscription:uuid}')->group(
                function (): void {
                    Route::patch('state', [VirtualMachineController::class, 'state'])->name('state');
                    Route::post('reinstall', [VirtualMachineController::class, 'reinstall'])->name('reinstall');
                    Route::post('reset-password', [VirtualMachineController::class, 'resetPassword'])->name('reset-password');
                    Route::post('reset-ssh', [VirtualMachineController::class, 'resetSshKey'])->name('reset-vm-sshkey');
                    Route::post('custom-name', [VirtualMachineController::class, 'customName'])->name('custom-name');
                    Route::get('reinstall-options', [VirtualMachineController::class, 'getAvailableReinstallOptions'])->name('reinstall-options');
                    Route::get('console', [VirtualMachineController::class, 'getConsoleUrl'])->name('console');
                }
            );
        });

        /**
         * SSH Keys.
         *
         * The GET and POST routes exclude the RequireVerifiedCustomer middleware because.
         * From Atlantis, it should be possible for a customer to select a key or add a key.
         * However, if it concerns a new customer, it could be a conversion killer because they will have to
         * go through a verification step during the order process.
         *
         */
        Route::prefix('ssh-key')->name('ssh-key.')->group(
            function (): void {
                Route::get('/', [SshKeyController::class, 'index'])->name('index')
                    ->withoutMiddleware(RequireVerifiedCustomer::class);
                Route::post('/', [SshKeyController::class, 'create'])->name('create')
                    ->withoutMiddleware(RequireVerifiedCustomer::class);
                Route::delete('/{sshKey:uuid}', [SshKeyController::class, 'destroy'])->name('destroy');
            }
        );
    }
);
