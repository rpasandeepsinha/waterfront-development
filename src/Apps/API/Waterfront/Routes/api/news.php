<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Waterfront\Controllers\NewsController;

Route::prefix('news')->group(
    function (): void {
        Route::get('/articles/{locale}', [NewsController::class, 'get'])->name('articles');
    },
);
