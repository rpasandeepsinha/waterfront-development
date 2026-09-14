<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Providers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Support\DeferrableProvider;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\DNS\DTO\DnsVanityNameserverConfigDto;
use Waterfront\Domain\DNS\Entities\DefaultDnsRecords;
use Waterfront\Domain\DNS\Entities\ExternalHostingDnsRecords;
use Waterfront\Domain\DNS\Entities\HostingDnsRecords;
use Waterfront\Domain\DNS\Entities\VpsDnsRecords;
use Waterfront\Domain\DNS\Generators\VanityNameserverGenerator;
use Waterfront\Domain\DNS\Hydrators\DnsRecordHydrator;
use Waterfront\Domain\DNS\Interfaces\DnsZoneFactoryInterface;
use Waterfront\Domain\DNS\Interfaces\NameserverAssignerInterface;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\Services\DnsVanityNameserverAssigner;
use Waterfront\Domain\DNS\Services\DnsZoneFactories\DnsZoneFromArrayTemplateFactory;
use Waterfront\Domain\DNS\Services\DnsZoneFactories\DnsZoneFromDatabaseTemplateFactory;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Support\Providers\BaseProvider;

class DnsServiceProvider extends BaseProvider implements DeferrableProvider
{
    public function boot(): void
    {
        $this->registerTranslations();
    }

    public function register(): void
    {
        $configuration = self::resolve(ConfigurationInterface::class);

        $this->app->bind(
            DnsZoneFromArrayTemplateFactory::class,
            fn (): DnsZoneFromArrayTemplateFactory => new DnsZoneFromArrayTemplateFactory(
                [
                    'default' => DefaultDnsRecords::getRecords(),
                    'hosting' => HostingDnsRecords::getRecords(),
                    'external-hosting' => ExternalHostingDnsRecords::getRecords(),
                    'vps' => VpsDnsRecords::getRecords(),
                ],
                $this->resolve(DnsRecordHydrator::class),
            ),
        );

        $vanityNameserverGenerator = new VanityNameserverGenerator();
        $this->app->instance(VanityNameserverGenerator::class, $vanityNameserverGenerator);

        $this->app->bind(DnsZoneFactoryInterface::class, fn (Container $container) => $configuration->getAsBoolean(
            'powerdnsclient.connection.use_faker',
        )
                ? $container->get(DnsZoneFromArrayTemplateFactory::class)
                : $container->get(DnsZoneFromDatabaseTemplateFactory::class));

        $this->app->singleton(
            DnsVanityNameserverConfigDto::class,
            static fn () => DnsVanityNameserverConfigDto::fromConfiguration($configuration),
        );

        $this->app->bind(
            NameserverAssignerInterface::class,
            fn (Container $container) => new DnsVanityNameserverAssigner(
                $container->get(VanityNameserverGenerator::class),
                $container->get(DnsDeploymentRepository::class),
                $container->get(LoggerInterface::class),
                $container->make(DnsVanityNameserverConfigDto::class),
            ),
        );
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            DnsZoneFromArrayTemplateFactory::class,
            DnsZoneFactoryInterface::class,
            DnsVanityNameserverConfigDto::class,
            NameserverAssignerInterface::class,
        ];
    }

    private function registerTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'dnsservice');
    }
}
