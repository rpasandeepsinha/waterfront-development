<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Providers;

use Illuminate\Foundation\Application;
use Psr\Log\LoggerInterface;
use RealtimeRegister\RealtimeRegister as RealtimeRegisterPackage;
use RealtimeRegister\Support\AuthorizedClient;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\RtrClient\Clients\RealtimeRegister;
use Waterfront\Infra\RtrClient\Services\RtrNotificationsService;
use Waterfront\Support\Providers\BaseProvider;

class RtrServiceProvider extends BaseProvider
{
    public function boot(): void
    {
        $this->publishes(
            [
                __DIR__ . '/../Config/config.php' => $this->app->configPath('rtrservice.php'),
            ],
            'config'
        );
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/connection.php',
            'realtimeregisterclient'
        );
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/handles.php',
            'realtimeregisterclient'
        );
    }

    public function register(): void
    {
        $configuration = $this->resolve(ConfigurationInterface::class);

        $this->app->bind(function () use ($configuration): RealtimeRegisterPackage {
            $key = $configuration->getAsString('realtimeregisterclient.connection.api_key');
            $url = $configuration->getAsString('realtimeregisterclient.connection.api_url');
            $verifySsl = $configuration->getAsBoolean('realtimeregisterclient.connection.verify_ssl');
            $logger = $this->resolve(LoggerInterface::class);

            $service = new RealtimeRegister($key, $url, $logger);
            $service->setClient(new AuthorizedClient($url, $key, ['verify' => $verifySsl], $logger));

            return $service;
        });

        $this->app->bind(RtrNotificationsService::class, fn (Application $app): RtrNotificationsService => new RtrNotificationsService(
            $app[RealtimeRegisterPackage::class],
            $app[LoggerInterface::class],
            $configuration->getAsString('realtimeregisterclient.handles.billing')
        ));
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            RealtimeRegisterPackage::class,
            RtrNotificationsService::class,
        ];
    }
}
