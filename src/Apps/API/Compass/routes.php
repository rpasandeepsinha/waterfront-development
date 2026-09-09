<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Kernel;

Route::middleware(Kernel::MIDDLEWARE_GROUP_COMPASS_API)
    ->as('admin.')
    ->prefix('admin')
    ->group(function (): void {
        foreach (File::files(__DIR__ . '/Routes') as $file) {
            require $file;
        }
    });
