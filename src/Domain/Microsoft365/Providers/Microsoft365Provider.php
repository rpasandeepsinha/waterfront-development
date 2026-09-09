<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use SandwaveIo\Office365\Office\OfficeClient;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Support\Providers\BaseProvider;

class Microsoft365Provider extends BaseProvider implements DeferrableProvider
{
    public function boot(): void
    {
        $this->registerConfig();
        $this->registerRoutes();
    }

    public function register(): void
    {
        $this->app->bind(function (): OfficeClient {
            $configuration = $this->resolve(ConfigurationInterface::class);
            return new OfficeClient(
                $configuration->getAsString('microsoft365.api_url'),
                $configuration->getAsString('microsoft365.api_user'),
                $configuration->getAsString('microsoft365.api_password'),
            );
        });
    }

    /** @return array<int, string> */
    public function provides(): array
    {
        return [
            OfficeClient::class,
        ];
    }

    private function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/microsoft365.php' => $this->app->configPath('microsoft365.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/microsoft365.php',
            'microsoft365'
        );
    }

    private function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/webhook.php');
    }
}
