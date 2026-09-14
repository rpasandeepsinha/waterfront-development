<?php

declare(strict_types=1);

namespace Waterfront\Infra\Microsoft\Graph\Provider;

use Illuminate\Contracts\Foundation\Application;
use SandwaveIo\Microsoft\Graph\GraphServiceClient;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\Microsoft\Graph\Config\ConnectorConfig;
use Waterfront\Infra\Microsoft\Graph\Factory\GraphServiceClientFactory;
use Waterfront\Support\Providers\BaseProvider;

class MicrosoftGraphClientServiceProvider extends BaseProvider
{
    public function boot(): void
    {
        // Optionally publish your config file if you decide later to add defaults.
        $this->publishes([
            __DIR__ . '/../Config/config.php' => $this->app->configPath('microsoftgraph.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            'microsoftgraph',
        );
    }

    public function register(): void
    {
        $this->app->singleton(function (Application $app): ConnectorConfig {
            $config = $this->resolve(ConfigurationInterface::class);

            return new ConnectorConfig(
                tenantId: $config->getAsString('microsoftgraph.tenant_id'),
                clientId: $config->getAsString('microsoftgraph.client_id'),
                clientSecret: $config->getAsString('microsoftgraph.client_secret'),
            );
        });

        $this->app->singleton(function (Application $app): GraphServiceClient {
            $factory = $this->resolve(GraphServiceClientFactory::class);

            return $factory->createForAdmin();
        });
    }
}
