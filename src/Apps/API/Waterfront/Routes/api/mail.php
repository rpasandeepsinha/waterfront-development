<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Waterfront\Controllers\MailController;

Route::prefix('domain/{domain}/mail')->name('mail.')->group(
    function (): void {
        Route::get('/', [MailController::class, 'configuration'])->name('configuration');

        Route::prefix('users')->group(
            function (): void {
                Route::get('/', [MailController::class, 'users'])->name('users');
                Route::post('/', [MailController::class, 'createUser'])->name('create-user');
                Route::post('/{username}/reset', [MailController::class, 'resetUser'])->name('reset-user');
                Route::delete('/{username}', [MailController::class, 'deleteUser'])->name('delete-user');
            }
        );
    }
);

Route::prefix('domain/{hostingDeployment:subscription_uuid}/mail')->name('mail.')->group(
    function (): void {
        Route::get('spamexperts', [MailController::class, 'spamExperts'])->name('spamexperts-sso');

        Route::prefix('forwards')->group(
            function (): void {
                Route::get('/', [MailController::class, 'forwards'])->name('forwards');
                Route::post('/', [MailController::class, 'createForward'])->name('create-forward');
                Route::delete('{source}', [MailController::class, 'deleteForward'])->name('delete-forward');
            }
        );
    }
);
