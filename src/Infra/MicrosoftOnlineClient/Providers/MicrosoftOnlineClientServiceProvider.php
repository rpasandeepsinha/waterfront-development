<?php

declare(strict_types=1);

namespace Waterfront\Infra\MicrosoftOnlineClient\Providers;

use Illuminate\Contracts\Foundation\Application;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\MicrosoftOnlineClient\Config\ConnectorConfig;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;
use Waterfront\Support\Providers\BaseProvider;

class MicrosoftOnlineClientServiceProvider extends BaseProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => $this->app->configPath('microsoftonlineclient.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            'microsoftonlineclient'
        );
    }

    public function register(): void
    {
        $this->app->singleton(function (Application $app): ConnectorConfig {
            $config = $this->resolve(ConfigurationInterface::class);

            return new ConnectorConfig(
                baseUrl: $config->getAsString('microsoftonlineclient.url'),
                verifySsl: $config->getAsBoolean('microsoftonlineclient.verify_ssl'),
                retryConfig: $this->resolve(RetryConfig::class),
            );
        });
    }
}
