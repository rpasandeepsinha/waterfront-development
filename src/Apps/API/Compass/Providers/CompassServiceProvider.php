<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Providers;

use Illuminate\Support\ServiceProvider;

class CompassServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');
    }
}
