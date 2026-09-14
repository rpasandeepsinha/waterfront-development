<?php

declare(strict_types=1);

namespace Waterfront\Infra\CaddyClient\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Waterfront\Infra\CaddyClient\Config\ConnectorConfig;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;
use Waterfront\Support\Providers\BaseProvider;

class CaddyClientServiceProvider extends BaseProvider implements DeferrableProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => $this->app->configPath('caddyclient.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            'caddyclient',
        );
    }

    public function register(): void
    {
        $this->app->singleton(function (Application $app): ConnectorConfig {
            $config = $this->resolve(ConfigurationInterface::class);

            return new ConnectorConfig(
                baseUrl: $config->getAsString('caddyclient.redirect_server'),
                username: $config->getAsString('caddyclient.redirect_username'),
                password: $config->getAsString('caddyclient.redirect_password'),
                retryConfig: $this->resolve(RetryConfig::class),
                verifySsl: $config->getAsBoolean('caddyclient.redirect_verify_ssl'),
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
