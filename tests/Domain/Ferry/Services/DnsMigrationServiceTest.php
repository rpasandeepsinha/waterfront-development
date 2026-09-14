<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Services;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use RealtimeRegister\RealtimeRegister;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\RtrClient\Helpers\MockedClientFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Services\DnsNameserverRetriever;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Ferry\Repositories\InternalNamserverRepository;
use Waterfront\Domain\Ferry\Services\DnsMigrationService;
use Waterfront\Domain\Ferry\Services\NameserverResolver;
use Waterfront\Infra\Configuration\ConfigurationInterface;

#[CoversClass(DnsMigrationService::class)]
#[AllowMockObjectsWithoutExpectations]
class DnsMigrationServiceTest extends IntegrationTestCase
{
    private const string TEST_DOMAIN = 'test-domain.testing';
    private const string TEST_REFERENCE_NUMBER = 'abc-abc';

    private DomainDeployment $domainDeployment;

    protected function setUp(): void
    {
        parent::setUp();

        $extensionProductGroup = ProductGroupFactory::new()->extension()->createOne();
        $dnsProductGroup = ProductGroupFactory::new()->dns()->createOne();

        $extensionProduct = ProductFactory::new()->for($extensionProductGroup)->createOne();
        $dnsProduct = ProductFactory::new()->for($dnsProductGroup)->createOne();

        $subscription = SubscriptionFactory::new()
            ->withCustomer()
            ->for($extensionProduct)
            ->forDomain(self::TEST_DOMAIN)
            ->technicalStatusDomainActive()
            ->createOne();

        $this->domainDeployment = DomainDeploymentFactory::new()->withRtrProvider()->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);

        $dnsSubscription = SubscriptionFactory::new()
            ->withCustomer()
            ->for($dnsProduct)
            ->forDomain(self::TEST_DOMAIN)
            ->parentSubscription($subscription)
            ->createOne();

        DnsDeploymentFactory::new()->for($dnsSubscription)->createOne();
    }

    /**
     * @param array<int, string> $nameservers
     * @param array<int, string> $resolvedNameservers
     */
    #[DataProvider('nameserverProvider')]
    #[Test]
    public function hasLegacyInternalNameservers(
        array $nameservers,
        array $resolvedNameservers,
        bool $expectInternal,
        bool $expectWhitelabel,
        bool $emptyNameservers,
    ): void {
        $domainDetailsResponse = include __DIR__ . '/data/domain_details_valid.php';
        $domainDetailsResponse['domain'] = self::TEST_DOMAIN;
        $domainDetailsResponse['ns'] = $emptyNameservers ? [] : $nameservers;

        $rtrSdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], json_encode($domainDetailsResponse, JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode($domainDetailsResponse, JSON_THROW_ON_ERROR)),
        ]);

        $this->app->bind(RealtimeRegister::class, static fn () => $rtrSdk);

        $nameserverResolver = self::createStub(NameserverResolver::class);
        $dnsMigrationService = new DnsMigrationService(
            configuration: self::resolve(ConfigurationInterface::class),
            dnsService: self::resolve(DnsService::class),
            dnsNameserverRetriever: self::resolve(DnsNameserverRetriever::class),
            domainService: self::resolve(DomainService::class),
            nameserverResolver: $nameserverResolver,
            internalNamserverRepository: self::resolve(InternalNamserverRepository::class),
            logger: self::resolve(LoggerInterface::class),
        );

        $nameserverResolver
            ->method('getNameserverIPs')
            ->willReturn(
                $expectWhitelabel
                    ? [
                        '1.2.3.4',
                        '5.6.7.8',
                    ]
                    : false,
            );

        $nameserverResolver
            ->method('getNameserverHostname')
            ->willReturn($emptyNameservers ? false : ($expectWhitelabel ? $resolvedNameservers[0] : false));

        $isInternal = $dnsMigrationService->hasLegacyInternalNameservers(
            subscriptionId: $this->domainDeployment->subscription->id,
            domain: self::TEST_DOMAIN,
            referenceCustomerNumber: self::TEST_REFERENCE_NUMBER,
            driver: $this->domainDeployment->provider->slug,
        );

        $isWhitelabel = $dnsMigrationService->hasLegacyWhitelabelNameservers(
            subscriptionId: $this->domainDeployment->subscription->id,
            domain: self::TEST_DOMAIN,
            referenceCustomerNumber: self::TEST_REFERENCE_NUMBER,
            driver: $this->domainDeployment->provider->slug,
        );

        self::assertSame($expectInternal, $isInternal);
        self::assertSame($expectWhitelabel, $isWhitelabel);
    }

    /**
     * See FERRY_MIGRATABLE_NAMESERVERS and FERRY_MIGRATABLE_NAMESERVERS_REGEX in .env.testing.
     *
     * @return iterable<string, mixed>
     */
    public static function nameserverProvider(): iterable
    {
        yield 'every nameserver is internal' => [
            'nameservers' => [
                'ns1.testing.test',
                'nameserver1337.testing.test',
            ],
            'resolvedNameservers' => [],
            'expectInternal' => true,
            'expectWhitelabel' => false,
            'emptyNameservers' => false,
        ];

        yield 'one internal and one external nameserver' => [
            'nameservers' => [
                'ns2.testing.test',
                'some-external-nameserver.test',
            ],
            'resolvedNameservers' => [],
            'expectInternal' => false,
            'expectWhitelabel' => false,
            'emptyNameservers' => false,
        ];

        yield 'one nameserver is external and one uses regex check' => [
            'nameservers' => [
                'nameserver1337.testing.test',
                'some-external-nameserver.test',
            ],
            'resolvedNameservers' => [],
            'expectInternal' => false,
            'expectWhitelabel' => false,
            'emptyNameservers' => false,
        ];

        yield 'every server is a whitelabel server' => [
            'nameservers' => [
                'ns1.whitelabel-server.test',
                'ns2.whitelabel-server.test',
            ],
            'resolvedNameservers' => [
                'ns1.testing.test',
                'ns2.testing.test',
            ],
            'expectInternal' => false,
            'expectWhitelabel' => true,
            'emptyNameservers' => false,
        ];

        yield 'one legacy and one whitelabel' => [
            'nameservers' => [
                'ns1.some-random-server.test',
                'ns2.some-random-server.test',
            ],
            'resolvedNameservers' => [
                'ns1.testing.test',
                'ns2.whitelabel-server.test',
            ],
            'expectInternal' => false,
            'expectWhitelabel' => false,
            'emptyNameservers' => false,
        ];

        yield 'has no nameservers configured at all!' => [
            'nameservers' => [], // empty array for hasLegacy
            'resolvedNameservers' => [], // empty array for whitelabel
            'expectInternal' => false, // internal check
            'expectWhitelabel' => false, // whitelabel check
            'emptyNameservers' => true, // should use empty array in rtr mock
        ];

        yield 'Nameservers with uppercase are internal' => [
            'nameservers' => [
                'NS1.TESTING.TEST',
                'NAMESERVER1337.TESTING.TEST',
            ],
            'resolvedNameservers' => [],
            'expectInternal' => true,
            'expectWhitelabel' => false,
            'emptyNameservers' => false,
        ];
    }
}
