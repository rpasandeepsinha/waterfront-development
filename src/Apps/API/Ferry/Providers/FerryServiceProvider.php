<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Providers;

use Illuminate\Support\ServiceProvider;

class FerryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerConfig();
        $this->registerRoutes();
    }

    private function registerConfig(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/ferry.php',
            'ferry'
        );
    }

    private function registerRoutes(): void
    {
        include __DIR__ . '/../routes.php';
    }
}
