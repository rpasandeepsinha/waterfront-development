<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenSrsClient\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Env;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\OpenSrsClient\Fakers\ConnectionFaker;
use Waterfront\Infra\OpenSrsClient\Fakers\OpenSrsClientFaker;
use Waterfront\Infra\OpenSrsClient\Interfaces\OpenSrsConnectionInterface;
use Waterfront\Infra\OpenSrsClient\Messages\Connection;
use Waterfront\Infra\OpenSrsClient\OpenSrsClient;
use Waterfront\Support\Providers\BaseProvider;

class OpenSrsClientProvider extends BaseProvider implements DeferrableProvider
{
    public function boot(): void
    {
        $this->publishes(
            [
                __DIR__ . '/../Config/connection.php' => $this->app->configPath('opensrsclient.php'),
            ],
            'config'
        );
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/connection.php',
            'opensrsclient'
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
            OpenSrsConnectionInterface::class,
            OpenSrsClient::class,
        ];
    }

    private function registerRealServices(): void
    {
        $this->app->bind(
            function (): OpenSrsConnectionInterface {
                $configuration = $this->resolve(ConfigurationInterface::class);

                return new Connection(
                    $configuration->getAsString('opensrsclient.connection.api_url'),
                    $configuration->getAsString('opensrsclient.connection.username'),
                    $configuration->getAsString('opensrsclient.connection.api_key'),
                );
            }
        );
    }

    private function registerFakeServices(): void
    {
        $this->app->bind(
            OpenSrsConnectionInterface::class,
            ConnectionFaker::class
        );
        $this->app->bind(
            OpenSrsClient::class,
            OpenSrsClientFaker::class
        );
    }
}
