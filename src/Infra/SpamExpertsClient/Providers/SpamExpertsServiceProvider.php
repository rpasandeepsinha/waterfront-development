<?php

declare(strict_types=1);

namespace Waterfront\Infra\SpamExpertsClient\Providers;

use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Env;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\SpamExpertsClient\Messages\Connection;
use Waterfront\Infra\SpamExpertsClient\SpamExpertsClient;
use Waterfront\Infra\SpamExpertsClient\SpamExpertsClientFaker;
use Waterfront\Support\Providers\BaseProvider;

class SpamExpertsServiceProvider extends BaseProvider
{
    public function boot(): void
    {
        $this->registerConfig();
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function register(): void
    {
        $configuration = $this->resolve(ConfigurationInterface::class);

        $this->app->singleton('spam-experts-http-client', function () use ($configuration): HttpClient {
            $connection = new Connection(
                $configuration->getAsString('spamexpertsclient.connection.api_url'),
                $configuration->getAsString('spamexpertsclient.connection.username'),
                $configuration->getAsString('spamexpertsclient.connection.password')
            );

            return new HttpClient([
                'base_uri'    => $connection->getApiUrl(),
                'headers'     => [
                    'Authorization' => 'Basic ' . $connection->getCredentials(),
                ],
                'http_errors' => false,
                'verify'      => $configuration->getAsBoolean('spamexpertsclient.connection.verify'),
            ]);
        });

        $this->app->when(SpamExpertsClient::class)
            ->needs(HttpClient::class)
            ->give(fn () => $this->app->get('spam-experts-http-client'));

        if (Env::get('APP_FAKE_SPAM_FILTER_CLIENT') === true) {
            $this->app->bind(
                SpamExpertsClient::class,
                SpamExpertsClientFaker::class
            );
        }
    }

    private function registerConfig(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/connection.php',
            'spamexpertsclient'
        );
    }
}
