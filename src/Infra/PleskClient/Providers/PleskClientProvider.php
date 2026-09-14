<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Providers;

use Illuminate\Support\Env;
use Illuminate\Support\ServiceProvider;
use Waterfront\Domain\Hosting\Interfaces\Hosting\CustomerInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingPackageInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SecretKeyInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SessionTokenInterface;
use Waterfront\Domain\Ssl\Interfaces\InstallInterface;
use Waterfront\Domain\Ssl\Interfaces\SelectInterface;
use Waterfront\Infra\PleskClient\Fakers\CustomerClientFaker;
use Waterfront\Infra\PleskClient\Fakers\HostingPackageClientFaker;
use Waterfront\Infra\PleskClient\Fakers\PleskClientFaker;
use Waterfront\Infra\PleskClient\PleskClient;
use Waterfront\Infra\PleskClient\Services\CustomerClient;
use Waterfront\Infra\PleskClient\Services\HostingPackageClient;

class PleskClientProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/connection.php',
            'hosting-service-client',
        );
    }

    public function register(): void
    {
        if (Env::get('APP_FAKE_HOSTING_CLIENT') === true) {
            $this->app->tag(HostingPackageClientFaker::class, HostingPackageInterface::class);
            $this->app->tag(CustomerClientFaker::class, CustomerInterface::class);
            $this->app->tag(PleskClientFaker::class, SessionTokenInterface::class);
            $this->app->tag(PleskClientFaker::class, InstallInterface::class);
            $this->app->tag(PleskClientFaker::class, SelectInterface::class);
            $this->app->tag(PleskClientFaker::class, SecretKeyInterface::class);

            // only added these binds in case final classes have this typehint.
            $this->app->bind(PleskClient::class, PleskClientFaker::class);
            $this->app->bind(HostingPackageClient::class, HostingPackageClientFaker::class);
            $this->app->bind(CustomerClient::class, CustomerClientFaker::class);
        } else {
            $this->app->tag(HostingPackageClient::class, HostingPackageInterface::class);
            $this->app->tag(CustomerClient::class, CustomerInterface::class);
            $this->app->tag(PleskClient::class, SessionTokenInterface::class);
            $this->app->tag(PleskClient::class, InstallInterface::class);
            $this->app->tag(PleskClient::class, SelectInterface::class);
            $this->app->tag(PleskClient::class, SecretKeyInterface::class);
        }
    }
}
