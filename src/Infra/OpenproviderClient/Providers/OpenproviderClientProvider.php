<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Env;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\OpenproviderClient\Fakers\ConnectionFaker;
use Waterfront\Infra\OpenproviderClient\Fakers\OpenproviderClientFaker;
use Waterfront\Infra\OpenproviderClient\Interfaces\OpenProviderConnectionInterface;
use Waterfront\Infra\OpenproviderClient\Messages\Connection;
use Waterfront\Infra\OpenproviderClient\OpenproviderClient;
use Waterfront\Support\Providers\BaseProvider;

class OpenproviderClientProvider extends BaseProvider implements DeferrableProvider
{
    public function boot(): void
    {
        $this->publishes(
            [
                __DIR__ . '/../Config/config.php' => $this->app->configPath('openproviderclient.php'),
            ],
            'config'
        );
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/connection.php',
            'openproviderclient'
        );
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/handles.php',
            'openproviderclient'
        );
    }

    public function register(): void
    {
        if (Env::get('APP_FAKE_DOMAIN_CLIENT') === true) {
            $this->registerFakeServices();
        } else {
            $this->registerRealServices();
        }
    }

    /** @return array<int, string> */
    public function provides(): array
    {
        return [
            OpenProviderConnectionInterface::class,
            OpenproviderClient::class,
        ];
    }

    private function registerRealServices(): void
    {
        $this->app->bind(
            function (): OpenProviderConnectionInterface {
                $configuration = $this->resolve(ConfigurationInterface::class);

                return new Connection(
                    $configuration->getAsString('openproviderclient.connection.api_url'),
                    $configuration->getAsString('openproviderclient.connection.username'),
                    $configuration->getAsString('openproviderclient.connection.password'),
                );
            }
        );
    }

    private function registerFakeServices(): void
    {
        $this->app->bind(
            OpenProviderConnectionInterface::class,
            ConnectionFaker::class
        );
        $this->app->bind(
            OpenproviderClient::class,
            OpenproviderClientFaker::class
        );
    }
}
