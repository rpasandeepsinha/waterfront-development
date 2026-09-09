<?php

declare(strict_types=1);

namespace Waterfront\Infra\GandiClient\Providers;

use Illuminate\Contracts\Foundation\Application;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\GandiClient\Config\ConnectorConfig;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;
use Waterfront\Support\Providers\BaseProvider;

class GandiClientServiceProvider extends BaseProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => $this->app->configPath('gandiclient.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            'gandiclient'
        );
    }

    public function register(): void
    {
        $this->app->singleton(function (Application $app): ConnectorConfig {
            $config = $this->resolve(ConfigurationInterface::class);

            return new ConnectorConfig(
                baseUrl: $config->getAsString('gandiclient.url'),
                authToken: $config->getAsString('gandiclient.token'),
                verifySsl: $config->getAsBoolean('gandiclient.verify_ssl'),
                retryConfig: $this->resolve(RetryConfig::class),
            );
        });
    }
}
