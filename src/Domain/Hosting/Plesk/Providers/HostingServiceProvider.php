<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Plesk\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Waterfront\Domain\Hosting\Interfaces\Hosting\ClientInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\CustomerInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingPackageInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingUsernameInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SecretKeyInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SessionTokenInterface;
use Waterfront\Domain\Hosting\Plesk\Services\ChainableHostingPackageClient;
use Waterfront\Domain\Hosting\Plesk\Services\ClientListStrategyFactory;
use Waterfront\Domain\Hosting\Plesk\Services\PleskUsernameBroker;
use Waterfront\Domain\Ssl\Interfaces\InstallInterface;
use Waterfront\Domain\Ssl\Interfaces\SelectInterface;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Support\Providers\BaseProvider;

class HostingServiceProvider extends BaseProvider implements DeferrableProvider
{
    public function boot(): void
    {
        $this->registerConfig();
    }

    public function register(): void
    {
        $configuration = self::resolve(ConfigurationInterface::class);

        $this->app->bind(HostingUsernameInterface::class, PleskUsernameBroker::class);

        $this->app->bind(HostingPackageInterface::class, ClientInterface::class);
        $this->app->bind(InstallInterface::class, ClientInterface::class);
        $this->app->bind(SecretKeyInterface::class, ClientInterface::class);
        $this->app->bind(SelectInterface::class, ClientInterface::class);
        $this->app->bind(SessionTokenInterface::class, ClientInterface::class);
        $this->app->bind(CustomerInterface::class, ClientInterface::class);
        $this->app->bind(ClientListStrategyFactory::class, fn (): ClientListStrategyFactory => new ClientListStrategyFactory($configuration->getAsBoolean('app.debug')));
        $this->app->singleton(function (): ClientInterface {
            /** @var ClientListStrategyFactory $factory */
            $factory = $this->app->get(ClientListStrategyFactory::class);
            $hostingClients = $factory->create(
                $this->app->tagged(HostingPackageInterface::class),
                HostingPackageInterface::class
            );
            $installClients = $factory->create(
                $this->app->tagged(InstallInterface::class),
                InstallInterface::class
            );
            $secretKeyClients = $factory->create(
                $this->app->tagged(SecretKeyInterface::class),
                SecretKeyInterface::class
            );
            $SelectInterfaceClients = $factory->create(
                $this->app->tagged(SelectInterface::class),
                SelectInterface::class
            );
            $sessionTokenClients = $factory->create(
                $this->app->tagged(SessionTokenInterface::class),
                SessionTokenInterface::class
            );
            $customerClient = $factory->create(
                $this->app->tagged(CustomerInterface::class),
                CustomerInterface::class
            );

            return new ChainableHostingPackageClient(
                $hostingClients,
                $installClients,
                $secretKeyClients,
                $SelectInterfaceClients,
                $sessionTokenClients,
                $customerClient
            );
        });
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            HostingUsernameInterface::class,
            HostingPackageInterface::class,
            InstallInterface::class,
            SecretKeyInterface::class,
            SelectInterface::class,
            SessionTokenInterface::class,
            CustomerInterface::class,
            ClientListStrategyFactory::class,
            ClientInterface::class,
        ];
    }

    private function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => $this->app->configPath('hostingservice.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/plesk.php',
            'hostingservice'
        );
    }
}
