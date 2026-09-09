<?php

declare(strict_types=1);

namespace Waterfront\Support\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Waterfront\HealthzController;

class HealthzRouteProvider extends RouteServiceProvider
{
    public function map(): void
    {
        Route::get('/healthz', [HealthzController::class, 'index']);
    }
}
