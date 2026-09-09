<?php

declare(strict_types=1);

namespace Waterfront\Infra\MollieClient\Providers;

use GuzzleHttp\Client;
use Illuminate\Config\Repository;
use Illuminate\Support\Env;
use Illuminate\Support\ServiceProvider;
use UnexpectedValueException;
use Waterfront\Domain\Payments\Interfaces\PaymentInterface;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\MollieClient\Fakers\MolliePaymentClientFaker;
use Waterfront\Infra\MollieClient\MollieCustomerClient;
use Waterfront\Infra\MollieClient\MollieMandateClient;
use Waterfront\Infra\MollieClient\MolliePaymentClient;
use Waterfront\Infra\MollieClient\Serializers\MollieSerializerFactory;

class MollieClientServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => $this->app->configPath('mollieclient.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            'mollieclient'
        );
    }

    public function register(): void
    {
        if (Env::get('APP_FAKE_PAYMENT_CLIENT') === true) {
            $this->app->bind(
                PaymentInterface::class,
                MolliePaymentClientFaker::class
            );
        } else {
            $this->app->bind(
                PaymentInterface::class,
                MolliePaymentClient::class
            );
            $this->app->when(MolliePaymentClient::class)
                ->needs(Client::class)
                ->give(function ($app): Client {
                    $config = $app->get(Repository::class);
                    $apiKey = $config->get('mollieclient.credentials.api_key');
                    if (! $apiKey) {
                        throw new UnexpectedValueException('I have no mollie api key');
                    }
                    $uri = $config->get('mollieclient.credentials.api_url');

                    return new Client([
                        'base_uri' => $uri,
                        'headers'  => [
                            'Authorization' => 'Bearer ' . $apiKey,
                        ],
                    ]);
                });
        }

        $this->app->bind(function ($app): MollieCustomerClient {
            $config = $app->get(ConfigurationInterface::class);
            $serializer = MollieSerializerFactory::getSerializer();

            return new MollieCustomerClient($config, $serializer, $config->getAsString('mollieclient.credentials.api_key'));
        });

        $this->app->bind(function ($app): MollieMandateClient {
            $config = $app->get(ConfigurationInterface::class);
            $serializer = MollieSerializerFactory::getSerializer();

            return new MollieMandateClient($config, $serializer, $config->getAsString('mollieclient.credentials.api_key'));
        });
    }

    /** @return array<int, string> */
    public function provides(): array
    {
        return [
            PaymentInterface::class,
            MollieCustomerClient::class,
            MollieMandateClient::class,
        ];
    }
}
