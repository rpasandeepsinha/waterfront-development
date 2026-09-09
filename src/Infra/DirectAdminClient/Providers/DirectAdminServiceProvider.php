<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Providers;

use Illuminate\Support\ServiceProvider;
use Waterfront\Infra\DirectAdminClient\BehavesAsDirectAdmin;
use Waterfront\Infra\DirectAdminClient\Connection\Server;
use Waterfront\Infra\DirectAdminClient\DirectAdmin;

/**
 * This provider will bootstrap the package for Laravel and register necessary configurations.
 */
class DirectAdminServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->app->singleton(BehavesAsDirectAdmin::class, fn () => new DirectAdmin(new Server()));
    }
}
