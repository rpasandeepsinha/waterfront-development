<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\Providers;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Psr\Log\LoggerInterface;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PuzzelClient\Config\ConnectorConfig;
use Waterfront\Infra\PuzzelClient\DTO\AccessPoint;
use Waterfront\Infra\PuzzelClient\DTO\PuzzelPublicCredentials;
use Waterfront\Infra\PuzzelClient\PuzzelPublicClient;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;
use Waterfront\Support\Providers\BaseProvider;

class PuzzelClientServiceProvider extends BaseProvider implements DeferrableProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => $this->app->configPath('puzzelclient.php'),
        ], 'config');

        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            'puzzelclient'
        );
    }

    public function register(): void
    {
        $this->app->singleton(function (Application $app): ConnectorConfig {
            $config = $this->resolve(ConfigurationInterface::class);

            return new ConnectorConfig(
                authUrl: $config->getAsString('puzzelclient.auth_url'),
                apiUrl: $config->getAsString('puzzelclient.api_url'),
                clientId: $config->getAsString('puzzelclient.client_id'),
                clientSecret: $config->getAsString('puzzelclient.client_secret'),
                tenantId: $config->getAsInteger('puzzelclient.tenant_id'),
                userId: $config->getAsInteger('puzzelclient.user_id'),
                accessPoint: new AccessPoint(
                    number: $config->getAsString('puzzelclient.access_point.number'),
                    countryCode: $config->getAsString('puzzelclient.access_point.country_code'),
                ),
                callbackQueue: $config->getAsString('puzzelclient.callback_queue'),
                retryConfig: $this->resolve(RetryConfig::class),
            );
        });

        $this->app->bind(function (Application $app): PuzzelPublicClient {
            $config = $this->resolve(ConfigurationInterface::class);

            return new PuzzelPublicClient(
                httpClient: new GuzzleClient([
                    'base_uri' => $config->getAsString('puzzelclient.public_api_url'),
                ]),
                logger: $app->get(LoggerInterface::class),
                credentials: new PuzzelPublicCredentials(
                    baseUrl: $config->getAsString('puzzelclient.public_api_url'),
                    clientId: $config->getAsString('puzzelclient.public_client_id'),
                    clientSecret: $config->getAsString('puzzelclient.public_client_secret'),
                ),
                cache: $app->make(CacheFactory::class)->store('redis'),
            );
        });
    }

    /**
     * @return class-string[]
     */
    public function provides(): array
    {
        return [ConnectorConfig::class, PuzzelPublicClient::class];
    }
}
