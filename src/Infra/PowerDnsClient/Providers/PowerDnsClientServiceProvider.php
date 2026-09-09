<?php

declare(strict_types=1);

namespace Waterfront\Infra\PowerDnsClient\Providers;

use Waterfront\Infra\PowerDnsClient\Clients\GuzzleClientFactory;
use Waterfront\Infra\PowerDnsClient\Clients\InternalPowerDnsClient;
use Waterfront\Support\Providers\BaseProvider;

class PowerDnsClientServiceProvider extends BaseProvider
{
    public function boot(): void
    {
        $this->registerConfig();
    }

    public function register(): void
    {
        $this->app->bind(InternalPowerDnsClient::class, fn () => new InternalPowerDnsClient($this->resolve(GuzzleClientFactory::class)->create()));
    }

    private function registerConfig(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/connection.php',
            'powerdnsclient'
        );
    }
}
