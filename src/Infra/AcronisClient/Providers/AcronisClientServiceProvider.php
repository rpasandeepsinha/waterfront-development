<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Waterfront\Infra\AcronisClient\Config\ConnectorConfig;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;
use Waterfront\Support\Providers\BaseProvider;

class AcronisClientServiceProvider extends BaseProvider implements DeferrableProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => $this->app->configPath('acronisclient.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            'acronisclient',
        );
    }

    public function register(): void
    {
        $this->app->singleton(function (Application $app): ConnectorConfig {
            $config = $this->resolve(ConfigurationInterface::class);

            return new ConnectorConfig(
                baseUrl: $config->getAsString('acronisclient.url'),
                clientId: $config->getAsString('acronisclient.client_id'),
                clientSecret: $config->getAsString('acronisclient.client_secret'),
                retryConfig: $this->resolve(RetryConfig::class),
            );
        });
    }

    /**
     * @return class-string[]
     */
    public function provides(): array
    {
        return [ConnectorConfig::class];
    }
}
