<?php

declare(strict_types=1);

namespace Waterfront\Domain\MailManagement\Providers;

use Illuminate\Support\ServiceProvider;

class MailManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/connection.php',
            'mailonly.connection',
        );

        $this->mergeConfigFrom(
            __DIR__ . '/../Config/ConnectionDetails/connection-details.php',
            'mailonly.connection_details',
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/connection.php' => $this->app->configPath('mailonly/connection.php'),
            __DIR__ . '/../Config/ConnectionDetails/connection-details.php' => $this->app->configPath(
                'mailonly/connection-details.php',
            ),
        ], 'mailonly-config');
    }
}
