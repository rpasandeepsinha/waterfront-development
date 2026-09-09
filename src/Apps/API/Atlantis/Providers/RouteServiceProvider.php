<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Atlantis\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Kernel;

class RouteServiceProvider extends ServiceProvider
{
    public function map(): void
    {
        Route::middleware(Kernel::MIDDLEWARE_GROUP_WATERFRONT_API)
            ->as('storefront.')
            ->prefix('storefront/api/v1')
            ->group(__DIR__ . '/../Routes/routes.php');

        Route::middleware(Kernel::MIDDLEWARE_GROUP_SYSTEM_AUTH_WEBHOOK)
            ->as('storefront.')
            ->prefix('storefront/api/v1')
            ->group(__DIR__ . '/../Routes/webhook.php');
    }
}
