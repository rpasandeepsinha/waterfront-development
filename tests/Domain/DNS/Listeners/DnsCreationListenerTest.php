<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Listeners;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Queue;
use JsonException;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DnsNameserverFactory;
use Tests\Factories\DnsRegionFactory;
use Tests\Factories\DnsVanityNameserverFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Actions\DisableZonePresigningAction;
use Waterfront\Domain\DNS\Actions\UpdateNameserverAndSoaAction;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Events\CreateDns;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Listeners\DnsCreationListener;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Observers\ProductObserver;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Provision\DNS\Events\DnsProvisioned;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;
use Waterfront\Support\Enums\Environment;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;

#[CoversClass(DnsCreationListener::class)]
#[AllowMockObjectsWithoutExpectations]
class DnsCreationListenerTest extends IntegrationTestCase
{
    private const string DOMAIN = 'testdomain.nl';
    private const string NS1 = 'ns1.testsandwave.nl';
    private const string NS2 = 'ns2.testsandwave.nl';
    private const string QUEUE = QueueName::DNS->value;

    private DomainDeployment $domainDeployment;

    private DnsService&MockInterface $dnsService;

    private LoggerInterface&MockInterface $logger;

    private UpdateNameserverAndSoaAction&MockInterface $updateNameserverAndSoaAction;

    private DisableZonePresigningAction&MockInterface $disableZonePresigningAction;

    private Dispatcher&MockInterface $eventDispatcher;

    private Subscription $domainSubscription;

    private DnsProductSpecRepository&MockObject $mockDnsProductSpecRepository;

    private DnsDeploymentRepository&Mockobject $mockDnsDeploymentRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMocks();
        Queue::fake();

        $product = new ProductFactory()->for(new ProductGroupFactory()->extension())->nlDomain();
        $this->domainSubscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->forDomain(self::DOMAIN)
            ->for(new CustomerFactory())
            ->for($product)
            ->createOne();

        $this->domainDeployment = new DomainDeploymentFactory()
            ->withRtrProvider()
            ->for($this->domainSubscription, 'subscription')
            ->createOne();

        ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::PLACEHOLDER,
            'enabled' => true,
            'default' => false,
        ]);

        $this->mockDnsProductSpecRepository = self::createMock(DnsProductSpecRepository::class);

        $this->mockDnsDeploymentRepository = self::createMock(DnsDeploymentRepository::class);
    }

    /**
     * @throws DnsZoneNotFoundException
     * @throws PdnsResponseException
     * @throws GuzzleException
     * @throws JsonException
     */
    #[Test]
    public function handleNonExistingZone(): void
    {
        $dnsSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->forDomain(self::DOMAIN)
            ->for(
                new ProductFactory()->freeDns(),
            )
            ->has(new DnsDeploymentFactory())
            ->for($this->domainSubscription, 'parent')
            ->createOne();

        $dnsDeployment = $dnsSubscription->dnsDeployment;
        self::assertNotNull($dnsDeployment);

        $this->mockDnsProductSpecRepository->expects(self::once())->method('isPremiumDns')->willReturn(false);

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->withArgs([
                'Creating DNS zone: {domain.name} (attempt: {job.attempt})',
                [
                    LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                    LoggingContextKeys::QUEUE_ATTEMPT => 1,
                ],
            ]);

        $this->dnsService->shouldReceive('hasDnsZone')->once()->with(self::DOMAIN)->andReturnFalse();

        $dnsRegion = new DnsRegionFactory()->set('name', 'test_region')->createOne();
        new DnsNameserverFactory()->for($dnsRegion)->createOne([
            'nameserver' => self::NS1,
        ]);

        new DnsNameserverFactory()->for($dnsRegion)->createOne([
            'nameserver' => self::NS2,
        ]);

        $this->domainDeployment->refresh();

        $nameserverHostnames = $dnsDeployment->dnsNameservers->pluck('nameserver')->toArray();
        $this->dnsService
            ->shouldReceive('createDnsZone')
            ->once()
            ->withArgs([
                self::DOMAIN,
                'default',
                null,
                null,
                false,
                $nameserverHostnames,
            ])
            ->andReturn(
                new DnsZone(new Fqdn(self::DOMAIN)),
            );

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(
                fn (DnsProvisioned $event) => (
                    $dnsSubscription->dnsDeployment !== null
                    && $dnsSubscription->dnsDeployment->id === $event->dnsDeployment->id
                ),
            );

        $dnsListener = new DnsCreationListener(
            dnsService: $this->dnsService,
            logger: $this->logger,
            updateNameserverAndSoaAction: $this->updateNameserverAndSoaAction,
            disableZonePresigningAction: $this->disableZonePresigningAction,
            dnsProductSpecRepository: $this->mockDnsProductSpecRepository,
            eventDispatcher: $this->eventDispatcher,
            dnsDeploymentRepository: $this->mockDnsDeploymentRepository,
            environment: self::resolve(Environment::class),
        );

        $dnsListener->handle(new CreateDns(
            $dnsSubscription->uuid,
            self::DOMAIN,
        ));

        self::assertSame(self::QUEUE, $dnsListener->queue);
        self::assertSame(TechnicalStatus::OK->value, $dnsSubscription->refresh()->technical_status);
    }

    #[Test]
    public function handlePremiumDns(): void
    {
        $expectedVanityArray = [
            new Nameserver('ns102.test-vanity.cldin.net'),
            new Nameserver('ns101.test-vanity.cldin.net'),
            new Nameserver('ns40.test-vanity.cldin.net'),
        ];

        $dnsPremiumProduct = new ProductFactory()->premiumDns()->createOne();
        new ProductSpecFactory()->for($dnsPremiumProduct)->createOne([
            'name' => ProductSpecName::DNS_IS_PREMIUM->value,
            'value' => true,
        ]);

        $dnsSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->forDomain(self::DOMAIN)
            ->for($dnsPremiumProduct)
            ->has(
                new DnsDeploymentFactory()->has(
                    new DnsVanityNameserverFactory()
                        ->count(3)
                        ->state(new Sequence(
                            ['nameserver' => $expectedVanityArray[0]->hostname],
                            ['nameserver' => $expectedVanityArray[1]->hostname],
                            ['nameserver' => $expectedVanityArray[2]->hostname],
                        )),
                    'vanityNameservers',
                )->premiumDns(),
            )
            ->for($this->domainSubscription, 'parent')
            ->createOne();

        $this->mockDnsProductSpecRepository->expects(self::once())->method('isPremiumDns')->willReturn(true);

        $this->logger
            ->shouldReceive('info')
            ->withArgs([
                'Creating DNS zone: {domain.name} (attempt: {job.attempt})',
                [
                    LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                    LoggingContextKeys::QUEUE_ATTEMPT => 1,
                ],
            ]);

        $this->dnsService->shouldReceive('hasDnsZone')->with(self::DOMAIN)->andReturnFalse();

        $this->dnsService
            ->shouldReceive('createDnsZone')
            ->once()
            ->withArgs([
                self::DOMAIN,
                'default',
                null,
                null,
                false,
                $expectedVanityArray,
            ])
            ->andReturn(
                new DnsZone(new Fqdn(self::DOMAIN)),
            );

        $this->dnsService->shouldReceive('enablePremiumDns')->once()->with(self::DOMAIN);

        $this->eventDispatcher->shouldReceive('dispatch')->never();

        $this->mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameservers')
            ->willReturn([
                $expectedVanityArray[0],
                $expectedVanityArray[1],
                $expectedVanityArray[2],
            ]);

        $dnsListener = new DnsCreationListener(
            dnsService: $this->dnsService,
            logger: $this->logger,
            updateNameserverAndSoaAction: $this->updateNameserverAndSoaAction,
            disableZonePresigningAction: $this->disableZonePresigningAction,
            dnsProductSpecRepository: $this->mockDnsProductSpecRepository,
            eventDispatcher: $this->eventDispatcher,
            dnsDeploymentRepository: $this->mockDnsDeploymentRepository,
            environment: self::resolve(Environment::class),
        );

        $dnsListener->handle(new CreateDns(
            $dnsSubscription->uuid,
            self::DOMAIN,
        ));

        self::assertSame(self::QUEUE, $dnsListener->queue);
        self::assertSame(TechnicalStatus::PENDING->value, $dnsSubscription->technical_status);
    }

    #[Test]
    public function handleExistingZonePremiumDns(): void
    {
        $expectedVanityArray = [
            new Nameserver('ns102.test-vanity.cldin.net'),
            new Nameserver('ns101.test-vanity.cldin.net'),
            new Nameserver('ns40.test-vanity.cldin.net'),
        ];

        $dnsSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->forDomain(self::DOMAIN)
            ->for(new ProductFactory()->premiumDns()->createOne())
            ->has(
                new DnsDeploymentFactory()->has(
                    new DnsVanityNameserverFactory()
                        ->count(3)
                        ->state(new Sequence(
                            ['nameserver' => $expectedVanityArray[0]->hostname],
                            ['nameserver' => $expectedVanityArray[1]->hostname],
                            ['nameserver' => $expectedVanityArray[2]->hostname],
                        )),
                    'vanityNameservers',
                )->premiumDns(),
            )
            ->for($this->domainSubscription, 'parent')
            ->createOne();

        $dnsDeployment = $dnsSubscription->dnsDeployment;

        $dnsRegion = new DnsRegionFactory()->set('name', 'test_region')->createOne();

        $ns1 = new DnsNameserverFactory()->for($dnsRegion)->createOne([
            'nameserver' => self::NS1,
        ]);

        $ns2 = new DnsNameserverFactory()->for($dnsRegion)->createOne([
            'nameserver' => self::NS2,
        ]);

        $dnsDeployment?->dnsNameservers()->saveMany([$ns1, $ns2]);

        $this->mockDnsProductSpecRepository->expects(self::once())->method('isPremiumDns')->willReturn(true);

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->withArgs([
                'Creating DNS zone: {domain.name} (attempt: {job.attempt})',
                [
                    LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                    LoggingContextKeys::QUEUE_ATTEMPT => 1,
                ],
            ]);

        $this->dnsService->shouldNotReceive('createDnsZone');

        $this->dnsService->shouldReceive('hasDnsZone')->once()->with(self::DOMAIN)->andReturnTrue();

        $this->dnsService->shouldReceive('isSlaveZone')->once()->with(self::DOMAIN)->andReturnTrue();

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->withArgs([
                'Create dns, zone already exists for domain {domain.name}. Updating kind to master',
                [LoggingContextKeys::DOMAIN_NAME => self::DOMAIN],
            ]);

        $this->dnsService->shouldReceive('changeToMasterAndEmptyMasters')->once()->with(self::DOMAIN);

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->withArgs([
                'Create dns, zone already exists for domain {domain.name}. Disabling presigned',
                [LoggingContextKeys::DOMAIN_NAME => self::DOMAIN],
            ]);

        $this->disableZonePresigningAction->shouldReceive('disable')->once()->with(self::DOMAIN);

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->withArgs([
                'Create dns, zone already exists for domain {domain.name}. Trying to update any legacy NS- and SOA-records',
                [LoggingContextKeys::DOMAIN_NAME => self::DOMAIN],
            ]);

        $this->updateNameserverAndSoaAction
            ->shouldReceive('updateRecords')
            ->once()
            ->withArgs([
                self::DOMAIN,
                $expectedVanityArray,
            ]);

        $this->dnsService->shouldReceive('createDnsZone')->never();

        $this->dnsService->shouldReceive('enablePremiumDns')->with(self::DOMAIN);

        $this->eventDispatcher->shouldReceive('dispatch')->never();

        $this->mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameservers')
            ->willReturn([
                $expectedVanityArray[0],
                $expectedVanityArray[1],
                $expectedVanityArray[2],
            ]);

        $dnsListener = new DnsCreationListener(
            dnsService: $this->dnsService,
            logger: $this->logger,
            updateNameserverAndSoaAction: $this->updateNameserverAndSoaAction,
            disableZonePresigningAction: $this->disableZonePresigningAction,
            dnsProductSpecRepository: $this->mockDnsProductSpecRepository,
            eventDispatcher: $this->eventDispatcher,
            dnsDeploymentRepository: $this->mockDnsDeploymentRepository,
            environment: self::resolve(Environment::class),
        );

        $dnsListener->handle(new CreateDns(
            $dnsSubscription->uuid,
            self::DOMAIN,
        ));

        self::assertSame(self::QUEUE, $dnsListener->queue);
        self::assertSame(TechnicalStatus::PENDING->value, $dnsSubscription->technical_status);
    }

    #[Test]
    public function handleExistingZone(): void
    {
        $dnsSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->forDomain(self::DOMAIN)
            ->for(
                new ProductFactory()->freeDns(),
            )
            ->for($this->domainSubscription, 'parent')
            ->has(new DnsDeploymentFactory())
            ->createOne();

        $dnsDeployment = $dnsSubscription->dnsDeployment;

        $dnsRegion = new DnsRegionFactory()->set('name', 'test_region')->createOne();

        $ns1 = new DnsNameserverFactory()->for($dnsRegion)->createOne([
            'nameserver' => self::NS1,
        ]);

        $ns2 = new DnsNameserverFactory()->for($dnsRegion)->createOne([
            'nameserver' => self::NS2,
        ]);

        $dnsDeployment?->dnsNameservers()->saveMany([$ns1, $ns2]);

        $this->mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameservers')
            ->with($dnsSubscription->dnsDeployment)
            ->willReturn([
                new Nameserver(self::NS1),
                new Nameserver(self::NS2),
            ]);

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->withArgs([
                'Creating DNS zone: {domain.name} (attempt: {job.attempt})',
                [
                    LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                    LoggingContextKeys::QUEUE_ATTEMPT => 1,
                ],
            ]);

        $this->dnsService->shouldNotReceive('createDnsZone');

        $this->dnsService->shouldReceive('hasDnsZone')->once()->with(self::DOMAIN)->andReturnTrue();

        $this->dnsService->shouldReceive('isSlaveZone')->once()->with(self::DOMAIN)->andReturnTrue();

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->withArgs([
                'Create dns, zone already exists for domain {domain.name}. Updating kind to master',
                [LoggingContextKeys::DOMAIN_NAME => self::DOMAIN],
            ]);

        $this->dnsService->shouldReceive('changeToMasterAndEmptyMasters')->once()->with(self::DOMAIN);

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->withArgs([
                'Create dns, zone already exists for domain {domain.name}. Disabling presigned',
                [LoggingContextKeys::DOMAIN_NAME => self::DOMAIN],
            ]);

        $this->disableZonePresigningAction->shouldReceive('disable')->once()->with(self::DOMAIN);

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->withArgs([
                'Create dns, zone already exists for domain {domain.name}. Trying to update any legacy NS- and SOA-records',
                [LoggingContextKeys::DOMAIN_NAME => self::DOMAIN],
            ]);

        $this->updateNameserverAndSoaAction
            ->shouldReceive('updateRecords')
            ->once()
            ->withArgs([
                self::DOMAIN,
                [new Nameserver(self::NS1), new Nameserver(self::NS2)],
            ]);

        $this->dnsService
            ->shouldReceive('createDnsZone')
            ->never()
            ->andReturn(
                new DnsZone(new Fqdn(self::DOMAIN)),
            );

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(
                fn (DnsProvisioned $event) => $dnsSubscription->dnsDeployment?->id === $event->dnsDeployment->id,
            );

        $dnsListener = new DnsCreationListener(
            dnsService: $this->dnsService,
            logger: $this->logger,
            updateNameserverAndSoaAction: $this->updateNameserverAndSoaAction,
            disableZonePresigningAction: $this->disableZonePresigningAction,
            dnsProductSpecRepository: $this->mockDnsProductSpecRepository,
            eventDispatcher: $this->eventDispatcher,
            dnsDeploymentRepository: $this->mockDnsDeploymentRepository,
            environment: self::resolve(Environment::class),
        );

        $dnsListener->handle(new CreateDns(
            $dnsSubscription->uuid,
            self::DOMAIN,
        ));

        self::assertSame(self::QUEUE, $dnsListener->queue);
    }

    private function createMocks(): void
    {
        $this->dnsService = self::mock(DnsService::class);
        $this->logger = self::mock(LoggerInterface::class);
        $this->updateNameserverAndSoaAction = self::mock(UpdateNameserverAndSoaAction::class);
        $this->disableZonePresigningAction = self::mock(DisableZonePresigningAction::class);

        $productObserver = self::createMock(ProductObserver::class);
        $this->app->bind(ProductObserver::class, fn () => $productObserver);

        $this->eventDispatcher = self::mock(Dispatcher::class);
    }
}
