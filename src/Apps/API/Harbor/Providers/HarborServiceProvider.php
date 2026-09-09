<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Harbor\Providers;

use Illuminate\Support\ServiceProvider;

class HarborServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/connection.php',
            'harbor-api-client'
        );

        $this->loadRoutesFrom(__DIR__ . '/../Routes/invoice.php');
    }
}
