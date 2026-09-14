<?php

declare(strict_types=1);

namespace Waterfront\Infra\Basekit\Providers;

use Illuminate\Contracts\Foundation\Application;
use Psr\Log\LoggerInterface;
use SandwaveIo\BaseKit\BaseKit;
use Waterfront\Infra\Basekit\Config\ConnectorConfig;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Support\Providers\BaseProvider;

class BasekitClientServiceProvider extends BaseProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => $this->app->configPath('basekit.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            'basekit',
        );
    }

    public function register(): void
    {
        $this->app->singleton(function (Application $app): ConnectorConfig {
            $config = $this->resolve(ConfigurationInterface::class);

            return new ConnectorConfig(
                baseUrl: $config->getAsString('basekit.base_url'),
                ssoUrl: $config->getAsString('basekit.sso_url'),
                username: $config->getAsString('basekit.username'),
                password: $config->getAsString('basekit.password'),
                brandReference: $config->getAsInteger('basekit.brand_reference'),
            );
        });

        $this->app->singleton(function (Application $app): BaseKit {
            $logger = $app->make(LoggerInterface::class);
            $config = $app->make(ConnectorConfig::class);

            return new BaseKit(
                username: $config->username,
                password: $config->password,
                baseUrl: $config->baseUrl,
                logger: $logger,
            );
        });
    }
}
