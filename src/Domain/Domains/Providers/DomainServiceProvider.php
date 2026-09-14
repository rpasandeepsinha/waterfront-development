<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Providers;

use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerConfig();
    }

    private function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => $this->app->configPath('domainservice.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/nameservers.php',
            'domainservice',
        );
    }
}
