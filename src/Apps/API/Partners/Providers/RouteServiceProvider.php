<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Partners\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Kernel;
use Waterfront\Apps\API\Middleware\AppendCustomerTelemetry;
use Waterfront\Apps\API\Middleware\RequireAuthenticatedCustomer;
use Waterfront\Apps\API\Middleware\RequireVerifiedCustomer;
use Waterfront\Apps\API\Middleware\RequireWaterfrontRelation;
use Waterfront\Infra\Configuration\ConfigurationInterface;

class RouteServiceProvider extends ServiceProvider
{
    /** @var string|null */
    protected $namespace = 'Waterfront\Apps\API\Waterfront\Controllers';

    public function map(): void
    {
        $this->mapApiRoutes();
    }

    private function mapApiRoutes(): void
    {
        /** @var ConfigurationInterface $configuration */
        $configuration = $this->app->make(ConfigurationInterface::class);
        $partnerApiUrl = $configuration->getAsString('app.url_partner_api');

        Route::namespace($this->namespace)
            ->domain($partnerApiUrl)
            ->middleware(Kernel::MIDDLEWARE_GROUP_WATERFRONT_API)
            ->as('partners.')
            ->prefix('partners/api/v1')
            ->group(__DIR__ . '/../../../../../src/Apps/API/Waterfront/Routes/api.php');

        Route::namespace($this->namespace)
            ->domain($partnerApiUrl)
            ->middleware(Kernel::MIDDLEWARE_GROUP_WATERFRONT_API)
            ->withoutMiddleware([
                RequireAuthenticatedCustomer::class,
                RequireVerifiedCustomer::class,
                RequireWaterfrontRelation::class,
                AppendCustomerTelemetry::class,
            ])
            ->as('partners.')
            ->prefix('partners/api/v1')
            ->group(__DIR__ . '/../../../../../src/Apps/API/Waterfront/Routes/unregistered.php');
    }
}
