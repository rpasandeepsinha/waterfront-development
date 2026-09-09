<?php

declare(strict_types=1);

namespace Tests\Domain\Domains\Services;

use Carbon\CarbonImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Exceptions\DnsDeploymentNotFoundException;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\Services\DnsNameserverAssigner;
use Waterfront\Domain\Domains\DTO\Handles;
use Waterfront\Domain\Domains\DTO\RetrieveResult;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Services\OpenproviderService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\OpenproviderClient\OpenproviderClient;
use Waterfront\Infra\OpenproviderClient\Services\NameServerRetriever;

#[CoversClass(OpenproviderService::class)]
#[AllowMockObjectsWithoutExpectations]
class OpenproviderServiceTest extends IntegrationTestCase
{
    private const string DOMAIN = 'test-domain.nl';

    private OpenproviderService $openproviderDomainService;

    private OpenproviderService $openproviderDomainServiceWithMocks;

    private DnsNameserverAssigner&MockObject $dnsNameserverAssignerMock;

    private DnsDeploymentRepository&MockObject $dnsDeploymentRepositoryMock;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openproviderDomainService = self::resolve(OpenproviderService::class);

        $this->dnsNameserverAssignerMock = self::createMock(DnsNameserverAssigner::class);
        $this->dnsDeploymentRepositoryMock = self::createMock(DnsDeploymentRepository::class);

        $this->openproviderDomainServiceWithMocks = new OpenproviderService(
            openproviderClient: self::resolve(OpenproviderClient::class),
            nameServerRetriever: self::resolve(NameServerRetriever::class),
            dnsService: self::resolve(DnsService::class),
            nameserverAssigner: $this->dnsNameserverAssignerMock,
            configuration: self::resolve(ConfigurationInterface::class),
            dnsDeploymentRepository:  $this->dnsDeploymentRepositoryMock
        );

        $productGroup = new ProductGroupFactory()->extension()->create();

        $this->subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->forDomain(self::DOMAIN)
            ->for(
                new ProductFactory()->nlDomain()
                    ->recycle($productGroup)
            )
            ->has(new DomainDeploymentFactory()->withOpenProvider())
            ->createOne();
    }

    #[Test]
    public function registerFailedMissingDnsDeployment(): void
    {
        $exceptionMessage = 'Dns deployment for domain: ' . self::DOMAIN . ' not found.';
        $this->expectException(DnsDeploymentNotFoundException::class);
        $this->expectExceptionMessageIs($exceptionMessage);

        $this->dnsDeploymentRepositoryMock->expects(self::once())
            ->method('getDnsDeploymentFromDomain')
            ->with(self::DOMAIN)
            ->willReturn(null);

        $this->dnsNameserverAssignerMock->expects(self::never())
            ->method('clear');

        $domainDeployment = $this->subscription->domainDeployment;
        self::assertInstanceOf(DomainDeployment::class, $domainDeployment);

        $this->openproviderDomainServiceWithMocks->register(
            deployment: $domainDeployment,
            period: 12,
            customer: $this->subscription->customer,
            handles: self::createStub(Handles::class)
        );
    }

    #[Test]
    public function registerFailedDueGenericException(): void
    {
        $dnsDeployment = DnsDeploymentFactory::new()->create([
        'subscription_uuid' => $this->subscription->uuid,
        ]);

        $this->dnsDeploymentRepositoryMock->expects(self::once())
            ->method('getDnsDeploymentFromDomain')
            ->with(self::DOMAIN)
            ->willReturn($dnsDeployment);

        $this->dnsNameserverAssignerMock->expects(self::once())
            ->method('clear')
            ->with($dnsDeployment);

        $this->expectException(RuntimeException::class);

        $domainDeployment = $this->subscription->domainDeployment;
        self::assertInstanceOf(DomainDeployment::class, $domainDeployment);
        $this->openproviderDomainServiceWithMocks->register(
            deployment: $domainDeployment,
            period: 12,
            customer: $this->subscription->customer,
            handles: self::createStub(Handles::class)
        );
    }

    #[Test]
    public function retrieveDnssec(): void
    {
        $dnssecKeys = $this->openproviderDomainService->retrieveDnssecKeys(self::DOMAIN);

        $expectedKeys = [[
            'protocol' => '3',
            'flags' => '257',
            'alg' => '13',
            'pubKey' => 'public/key+contents==',
        ]];

        self::assertSame($expectedKeys, $dnssecKeys);
    }

    #[Test]
    public function isDnssecSupported(): void
    {
        $nlSupported = $this->openproviderDomainService->isDnssecSupported('dnssecissupported.nl');
        self::assertTrue($nlSupported, '.nl should support DNSSEC');

        $aiSupported = $this->openproviderDomainService->isDnssecSupported('dnssecissupported.ai');
        self::assertFalse($aiSupported, '.ai should not support DNSSEC');
    }

    #[Test]
    public function retrieveRenewalDate(): void
    {
        $actualExpireDate = CarbonImmutable::tomorrow()->format(DateTimeFormat::DEFAULT);
        $results = new RetrieveResult();
        $results->setExpirationDateOpenprovider($actualExpireDate);

        $mockOpenProvider = self::createMock(OpenproviderClient::class);
        $mockOpenProvider->expects(self::once())->method('retrieveDomain')->willReturn($results);

        $this->app->bind(OpenproviderClient::class, fn () => $mockOpenProvider);

        $domainService = self::resolve(OpenproviderService::class);

        $renewalDate = $domainService->retrieveRenewalDate(self::DOMAIN);

        self::assertTrue($renewalDate->eq(CarbonImmutable::createFromTimeString($actualExpireDate)));
        self::assertTrue($renewalDate->isFuture());
    }

    #[Test]
    public function retrieveRenewalDateNullException(): void
    {
        $mockOpenProvider = self::createMock(OpenproviderClient::class);
        $mockOpenProvider->expects(self::once())->method('retrieveDomain')->willReturn(new RetrieveResult());

        $this->app->instance(OpenproviderClient::class, $mockOpenProvider);

        $domainService = self::resolve(OpenproviderService::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('Could not retrieve date from OpenProvider for domain test-domain.nl');

        $domainService->retrieveRenewalDate(self::DOMAIN);
    }

    #[Test]
    public function updateNameservers(): void
    {
        $domainService = self::resolve(OpenproviderService::class);

        $nameservers = [
            new Nameserver('nameserverhostname1.nl', '127.0.0.1', '::1'),
            new Nameserver('nameserverhostname2.nl', null, '::2'),
        ];

        $success = $domainService->updateNameServers('example.nl', $nameservers);

        self::assertTrue($success);
    }
}
