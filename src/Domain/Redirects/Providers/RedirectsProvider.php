<?php

declare(strict_types=1);

namespace Waterfront\Domain\Redirects\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Services\DnsZoneService;
use Waterfront\Domain\Redirects\Services\RedirectDnsService;
use Waterfront\Domain\Redirects\Services\RedirectDnsServiceInterface;
use Waterfront\Domain\Redirects\Services\RedirectsDatabase\RedirectDatabaseRepository;
use Waterfront\Domain\Redirects\Services\RedirectsDatabase\RedirectsRepositoryInterface;
use Waterfront\Domain\Redirects\Services\RedirectService;
use Waterfront\Domain\Redirects\Services\RedirectServiceInterface;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Support\Providers\BaseProvider;

class RedirectsProvider extends BaseProvider implements DeferrableProvider
{
    public function boot(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/database.php',
            'database.connections.redirects',
        );

        $this->mergeConfigFrom(
            __DIR__ . '/../Config/service.php',
            'redirects.service',
        );
    }

    public function register(): void
    {
        $configuration = $this->resolve(ConfigurationInterface::class);
        $this->app->bind(RedirectsRepositoryInterface::class, RedirectDatabaseRepository::class);

        $this->app->bind(RedirectDnsServiceInterface::class, fn (): RedirectDnsServiceInterface => new RedirectDnsService(
            $this->resolve(DnsService::class),
            $this->resolve(PublicSuffixList::class),
            $this->resolve(LoggerInterface::class),
            $configuration,
            $this->resolve(DnsZoneService::class),
        ));

        $this->app->bind(RedirectServiceInterface::class, RedirectService::class);
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            RedirectServiceInterface::class,
            RedirectDnsServiceInterface::class,
            RedirectsRepositoryInterface::class,
        ];
    }
}
