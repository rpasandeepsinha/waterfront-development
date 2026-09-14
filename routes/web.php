<?php

declare(strict_types=1);

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Kernel;
use Waterfront\Apps\Nova\Controllers\ExportController;

// Export route for downloading files from Nova. Should only be accessible for employees.
Route::get('export/{filename}', [
    ExportController::class,
    'download',
])
    ->name('export')
    ->withoutMiddleware(Kernel::MIDDLEWARE_GROUP_WEB)
    ->middleware([
        Kernel::MIDDLEWARE_GROUP_COMPASS_API,
    ]);

// Requests to / on the admin URL should redirect to /nova
$domain = Config::get('nova.domain');
assert(is_string($domain));

Route::domain($domain)
    ->name('root')
    ->middleware([Kernel::MIDDLEWARE_GROUP_NOVA])
    ->withoutMiddleware(Kernel::MIDDLEWARE_GROUP_WEB)
    ->get('/', fn (Request $request) => new RedirectResponse('/nova'));
