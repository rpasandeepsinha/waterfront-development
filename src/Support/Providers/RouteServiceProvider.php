<?php

declare(strict_types=1);

namespace Waterfront\Support\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Kernel;

class RouteServiceProvider extends ServiceProvider
{
    /** @var string|null */
    protected $namespace = 'App\Http\Controllers';

    public function map(): void
    {
        Route::middleware(Kernel::MIDDLEWARE_GROUP_WEB)
            ->namespace($this->namespace)
            ->group($this->app->basePath('routes/web.php'));
    }
}
