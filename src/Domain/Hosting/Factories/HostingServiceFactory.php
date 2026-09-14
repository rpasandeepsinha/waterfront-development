<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Factories;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;
use Waterfront\Domain\Hosting\DirectAdmin\Services\DirectAdminHostingService;
use Waterfront\Domain\Hosting\Interfaces\Drivers\HostingServiceInterface;
use Waterfront\Domain\Hosting\Plesk\Services\PleskHostingService;
use Waterfront\Domain\Placeholder\Services\HostingPlaceholderService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Provision\Hosting\Exceptions\DriverNotDefinedException;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;

class HostingServiceFactory
{
    public function __construct(
        private readonly HostingPlaceholderService $placeholderService,
        private readonly DirectAdminHostingService $directAdminHostingService,
        private readonly PleskHostingService $pleskHostingService,
    ) {
    }

    /**
     * @throws DriverNotDefinedException
     */
    public function driver(ProviderSlug $driver): HostingServiceInterface
    {
        return match ($driver) {
            ProviderSlug::PLACEHOLDER => $this->placeholderService,
            ProviderSlug::DIRECTADMIN => $this->directAdminHostingService,
            ProviderSlug::PLESK => $this->pleskHostingService,
            ProviderSlug::BASEKIT,
            ProviderSlug::ACRONIS,
            ProviderSlug::REALTIME_REGISTER,
            ProviderSlug::XOLPHIN,
            ProviderSlug::OPEN_PROVIDER,
                => throw new RuntimeException(),
        };
    }

    /**
     * @throws ModelNotFoundException
     */
    public function defaultDriver(): HostingServiceInterface
    {
        $provider = Provider::where('default', true)->where('type', ProviderType::HOSTING)->firstOrFail();

        return $this->driver($provider->slug);
    }

    public function getDriverFromServer(Server $server): ProviderSlug
    {
        return match ($server->type) {
            ServerType::DIRECTADMIN, ServerType::DIRECTADMIN_MAIL => ProviderSlug::DIRECTADMIN,
            ServerType::PLESK => ProviderSlug::PLESK,
            default => throw new DriverNotDefinedException(sprintf(
                'Could not resolve a driver from a hosting server ID: {%d} with hostname: {%s}',
                $server->id,
                $server->hostname,
            )),
        };
    }
}
