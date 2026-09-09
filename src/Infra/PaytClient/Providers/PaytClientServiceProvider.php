<?php

declare(strict_types=1);

namespace Waterfront\Infra\PaytClient\Providers;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PaytClient\Factory\PaytClientFactory;
use Waterfront\Infra\PaytClient\PaytMandateClient;
use Waterfront\Infra\PaytClient\Serializers\PaytSerializerFactory;

class PaytClientServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => $this->app->configPath('paytclient.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            'paytclient'
        );
    }

    public function register(): void
    {
        $this->app->bind(function ($app): PaytMandateClient {
            $config = $app->get(ConfigurationInterface::class);
            $serializer = PaytSerializerFactory::getSerializer();
            $logger = $app->get(LoggerInterface::class);

            return new PaytMandateClient($config, $serializer, $logger, $config->getAsString('paytclient.api_key'));
        });

        $this->app->bind(function ($app): PaytClientFactory {
            $config = $app->get(ConfigurationInterface::class);

            return new PaytClientFactory(
                httpClient: new GuzzleClient([
                    'base_uri' => $config->getAsString('paytclient.api_url'),
                ]),
                logger: $app->get(LoggerInterface::class),
                serializer: PaytSerializerFactory::getSerializer(),
                config: $config,
            );
        });
    }
}
