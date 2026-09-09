<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Jobs;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Saloon\Exceptions\Request\Statuses\ForbiddenException;
use Saloon\Exceptions\Request\Statuses\NotFoundException;
use Saloon\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DnsVanityNameserverFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Actions\UpdateNameserverAndSoaAction;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Jobs\UpdateNameservers;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Interfaces\DomainDriverInterface;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Provision\DNS\Events\DnsProvisioned;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\GandiClient\GandiClient;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;

#[CoversClass(UpdateNameservers::class)]
class UpdateNameserversTest extends IntegrationTestCase
{
    public const string DOMAIN = 'update-ns.nl';

    #[Test]
    public function updateNameServersDispatch(): void
    {
        Queue::fake();
        Queue::assertNothingPushed();

        self::resolve(Dispatcher::class)
            ->dispatch(
                new UpdateNameservers(
                    self::DOMAIN,
                    true
                )
            );

        Queue::assertPushedOn(QueueName::DNS->value, UpdateNameservers::class);
    }

    #[Test]
    public function updateNameServersDispatchAsync(): void
    {
        Bus::fake();

        self::resolve(Dispatcher::class)
            ->dispatch(
                new UpdateNameservers(
                    self::DOMAIN,
                    true
                )
            );

        Bus::assertNotDispatchedSync(UpdateNameservers::class);
    }

    #[Test]
    public function failedJobSetsTechnicalStatusToFailed(): void
    {
        $mockLogger = self::createMock(LoggerInterface::class);
        $this->app->bind(LoggerInterface::class, fn () => $mockLogger);
        $subscriptionRepo = self::createMock(SubscriptionRepository::class);
        $this->app->bind(SubscriptionRepository::class, fn () => $subscriptionRepo);

        $mockLogger->expects(self::once())
            ->method('error')
            ->with(
                'Error UpdateNameServers for domain {domain.name} job definitely failed after {job.attempt} attempts'
            );

        $customExceptionMessage = '404 not found';

        $dnsSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->forDomain(self::DOMAIN)
            ->for(new ProductFactory()->premiumDns())
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->createOne();

        $subscriptionRepo->expects(self::once())
            ->method('getNotAdministrativelyEndedOrSuspendedDnsSubscription')
            ->willReturn($dnsSubscription);

        $job = new UpdateNameservers(self::DOMAIN, true);

        $job->failed(new NotFoundHttpException($customExceptionMessage));

        self::assertSame(TechnicalStatus::FAILED->value, $dnsSubscription->refresh()->technical_status);
    }

    #[Test]
    public function handleSuccessWithVanityNS(): void
    {
        $mockGandiClient = self::createMock(GandiClient::class);
        $updateNsActionMock = self::createMock(UpdateNameserverAndSoaAction::class);
        $mockLogger = self::mock(LoggerInterface::class);
        $subscriptionRepo = self::createMock(SubscriptionRepository::class);
        $mockDomainDeploymentRepository = self::createMock(DomainDeploymentRepository::class);
        $mockDnsDeploymentRepository = self::createMock(DnsDeploymentRepository::class);
        $mockDnsService = self::createMock(DnsService::class);
        $mockEventDispatcher = self::createMock(EventDispatcher::class);

        $testDomain = 'update-ns.nl';

        $expectedVanityHostnames = [
            'ns155.test-vanity.cldin.net',
            'ns20.test-vanity.cldin.net',
            'ns191.test-vanity.cldin.net',
        ];

        $expectedVanityArray = [
            new Nameserver($expectedVanityHostnames[0]),
            new Nameserver($expectedVanityHostnames[1]),
            new Nameserver($expectedVanityHostnames[2]),
        ];
        $expectForceAdjustment = true;
        $gandiFakeResponse = [
            'rrset_name' => 'update-ns.nl',
            'rrset_type' => 'NS',
            'rrset_ttl' => 10800,
            'rrset_values' => [
                'ns1.cldin.net',
                'ns2.cldin.net',
                'ns3.cldin.net',
            ],
        ];

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->forDomain($testDomain)
            ->for(
                new ProductFactory()
                    ->for(new ProductGroupFactory()->extension())
                    ->nlDomain()
            )
            ->createOne();

        $dnsSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->forDomain(self::DOMAIN)
            ->for(new ProductFactory()->premiumDns())
            ->has(
                new DnsDeploymentFactory()
                    ->has(
                        new DnsVanityNameserverFactory()
                            ->count(3)
                            ->state(
                                new Sequence(
                                    ['nameserver' => $expectedVanityHostnames[0]],
                                    ['nameserver' => $expectedVanityHostnames[1]],
                                    ['nameserver' => $expectedVanityHostnames[2]],
                                )
                            ),
                        'vanityNameservers'
                    )
            )
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->createOne();

        $dnsSubscription->save();

        $domainDeployment = new DomainDeploymentFactory()->withRtrProvider()
            ->for($subscription, 'subscription')
            ->createOne();

        $mockDomainDeploymentRepository
            ->expects(self::once())
            ->method('getActiveDeploymentByDomain')
            ->with($testDomain)
            ->willReturn($domainDeployment);

        $mockGandiClient->expects(self::once())
            ->method('getDnsRecords')
            ->with($testDomain)
            ->willReturn($gandiFakeResponse);

        $mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('saveLastPremiumProviderResponse')
            ->with($dnsSubscription->dnsDeployment, json_encode($gandiFakeResponse));

        $mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameservers')
            ->with($dnsSubscription->dnsDeployment)
            ->willReturn($expectedVanityArray);

        $mockLogger->shouldReceive('debug')
            ->once()
            ->with(
                'Starting UpdateNameServers Job for domain [{domain.name}]. attempt {job.attempt}/{job.max_attempts}',
                [
                    LoggingContextKeys::DOMAIN_NAME => $testDomain,
                    LoggingContextKeys::QUEUE_ATTEMPT => 1,
                    LoggingContextKeys::META => [
                        'useVanityNs' => 'true',
                    ],
                ]
            );

        $mockLogger->shouldReceive('debug')
            ->once()
            ->withArgs(function (string $message, array $context) use ($testDomain, $expectedVanityHostnames) {
                self::assertSame('Successful deployment for [{domain.name}] at Gandi', $message);

                self::assertArrayHasKey(LoggingContextKeys::DOMAIN_NAME, $context);
                self::assertArrayHasKey(LoggingContextKeys::META, $context);

                self::assertSame($context[LoggingContextKeys::DOMAIN_NAME], $testDomain);

                self::assertIsArray($context[LoggingContextKeys::META]);
                self::assertArrayHasKey('nameservers', $context[LoggingContextKeys::META]);

                foreach ($context[LoggingContextKeys::META]['nameservers'] as $nameserverObject) {
                    self::assertInstanceOf(Nameserver::class, $nameserverObject);
                    self::assertContains($nameserverObject->hostname, $expectedVanityHostnames);
                }

                return true;
            });

        $mockDnsService->expects(self::once())
            ->method('sendNotify')
            ->with($testDomain);

        $subscriptionRepo->expects(self::once())
            ->method('getNotAdministrativelyEndedOrSuspendedDnsSubscription')
            ->willReturn($dnsSubscription);

        $updateNsActionMock->expects(self::once())
            ->method('updateRecords')
            ->with($testDomain, $expectedVanityArray, $expectForceAdjustment);

        $mockEventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(
                self::callback(
                    fn (
                        DnsProvisioned $dnsProvisioned
                    ) => $dnsProvisioned->dnsDeployment->id === $dnsSubscription->dnsDeployment?->id
                )
            );

        $updateNsJob = new UpdateNameservers($testDomain, true);
        $updateNsJob->handle(
            gandiClient: $mockGandiClient,
            updateNameserverAndSoaAction: $updateNsActionMock,
            logger: $mockLogger,
            subscriptionRepository: $subscriptionRepo,
            domainDeploymentRepository: $mockDomainDeploymentRepository,
            dnsDeploymentRepository: $mockDnsDeploymentRepository,
            dnsService: $mockDnsService,
            eventDispatcher: $mockEventDispatcher,
        );

        self::assertSame(TechnicalStatus::OK->value, $dnsSubscription->refresh()->technical_status);
    }

    #[Test]
    public function premiumResultsSavedWithVanityNsFailure(): void
    {
        self::createStub(DomainServiceFactory::class);
        $mockGandiClient = self::createMock(GandiClient::class);
        $updateNsActionMock = self::createStub(UpdateNameserverAndSoaAction::class);
        $mockLogger = self::createMock(LoggerInterface::class);
        self::createStub(DomainDriverInterface::class);
        $subscriptionRepo = self::createMock(SubscriptionRepository::class);
        $mockDomainDeploymentRepository = self::createMock(DomainDeploymentRepository::class);
        $mockDnsDeploymentRepository = self::createMock(DnsDeploymentRepository::class);
        $mockDnsService = self::createStub(DnsService::class);
        $mockGandiException = self::createMock(ForbiddenException::class);
        $mockSaloonResponse = self::createMock(Response::class);
        $mockEventDispatcher = self::createMock(EventDispatcher::class);

        $testDomain = 'update-ns.nl';
        $expectedVanityArray = [
            'ns155.test-vanity.cldin.net',
            'ns20.test-vanity.cldin.net',
            'ns191.test-vanity.cldin.net',
        ];
        $gandiFakeResponse = json_encode([
            'message' => 'Zone not found',
            'status'  => 404,
        ]);

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->forDomain($testDomain)
            ->for(
                new ProductFactory()
                    ->for(new ProductGroupFactory()->extension())
                    ->nlDomain()
            )
            ->createOne();

        $dnsSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->forDomain(self::DOMAIN)
            ->for(new ProductFactory()->premiumDns())
            ->has(
                new DnsDeploymentFactory()
                    ->has(
                        new DnsVanityNameserverFactory()
                            ->count(3)
                            ->state(
                                new Sequence(
                                    ['nameserver' => $expectedVanityArray[0]],
                                    ['nameserver' => $expectedVanityArray[1]],
                                    ['nameserver' => $expectedVanityArray[2]],
                                )
                            ),
                        'vanityNameservers'
                    )
            )
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->createOne();

        $domainDeployment = new DomainDeploymentFactory()->withRtrProvider()
            ->for($subscription, 'subscription')
            ->createOne();

        $mockDomainDeploymentRepository
            ->expects(self::once())
            ->method('getActiveDeploymentByDomain')
            ->with($testDomain)
            ->willReturn($domainDeployment);

        $mockSaloonResponse->expects(self::once())
            ->method('body')
            ->willReturn($gandiFakeResponse);

        $mockGandiException->expects(self::once())
            ->method('getResponse')
            ->willReturn($mockSaloonResponse);

        $mockGandiClient->expects(self::once())
            ->method('getDnsRecords')
            ->with($testDomain)
            ->willThrowException($mockGandiException);

        $mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('saveLastPremiumProviderResponse')
            ->with($dnsSubscription->dnsDeployment, $gandiFakeResponse);

        $mockLogger->expects(self::once())
            ->method('debug')
            ->with('Starting UpdateNameServers Job for domain [{domain.name}]. attempt {job.attempt}/{job.max_attempts}');

        self::expectException($mockGandiException::class);

        $subscriptionRepo->expects(self::once())
            ->method('getNotAdministrativelyEndedOrSuspendedDnsSubscription')
            ->willReturn($dnsSubscription);

        $mockEventDispatcher->expects(self::never())
            ->method('dispatch');

        $updateNsJob = new UpdateNameservers($testDomain, true);
        $updateNsJob->handle(
            gandiClient: $mockGandiClient,
            updateNameserverAndSoaAction: $updateNsActionMock,
            logger: $mockLogger,
            subscriptionRepository: $subscriptionRepo,
            domainDeploymentRepository: $mockDomainDeploymentRepository,
            dnsDeploymentRepository: $mockDnsDeploymentRepository,
            dnsService: $mockDnsService,
            eventDispatcher: $mockEventDispatcher,
        );

        self::assertSame(TechnicalStatus::FAILED->value, $dnsSubscription->refresh()->technical_status);
    }

    #[Test]
    public function premiumResultsSavedWithVanityNsFailureNotFound(): void
    {
        self::createStub(DomainServiceFactory::class);
        $mockGandiClient = self::createMock(GandiClient::class);
        $updateNsActionMock = self::createStub(UpdateNameserverAndSoaAction::class);
        $mockLogger = self::createMock(LoggerInterface::class);
        self::createStub(DomainDriverInterface::class);
        $subscriptionRepo = self::createMock(SubscriptionRepository::class);
        $mockDomainDeploymentRepository = self::createMock(DomainDeploymentRepository::class);
        $mockDnsDeploymentRepository = self::createMock(DnsDeploymentRepository::class);
        $mockDnsService = self::createStub(DnsService::class);
        $mockGandiException = self::createMock(NotFoundException::class);
        $mockEventDispatcher = self::createMock(EventDispatcher::class);

        $testDomain = 'update-ns.nl';
        $expectedVanityArray = [
            'ns155.test-vanity.cldin.net',
            'ns20.test-vanity.cldin.net',
            'ns191.test-vanity.cldin.net',
        ];
        json_encode([
            'message' => 'Zone not found',
            'status'  => 404,
        ]);

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->forDomain($testDomain)
            ->for(
                new ProductFactory()
                    ->for(new ProductGroupFactory()->extension())
                    ->nlDomain()
            )
            ->createOne();

        $dnsSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->forDomain(self::DOMAIN)
            ->for(new ProductFactory()->premiumDns())
            ->has(
                new DnsDeploymentFactory()
                    ->has(
                        new DnsVanityNameserverFactory()
                            ->count(3)
                            ->state(
                                new Sequence(
                                    ['nameserver' => $expectedVanityArray[0]],
                                    ['nameserver' => $expectedVanityArray[1]],
                                    ['nameserver' => $expectedVanityArray[2]],
                                )
                            ),
                        'vanityNameservers'
                    )
            )
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->createOne();

        $domainDeployment = new DomainDeploymentFactory()->withRtrProvider()
            ->for($subscription, 'subscription')
            ->createOne();

        $mockDomainDeploymentRepository
            ->expects(self::once())
            ->method('getActiveDeploymentByDomain')
            ->with($testDomain)
            ->willReturn($domainDeployment);

        $mockGandiException->expects(self::never())
            ->method('getResponse');

        $mockGandiClient->expects(self::once())
            ->method('getDnsRecords')
            ->with($testDomain)
            ->willThrowException($mockGandiException);

        $mockDnsDeploymentRepository
            ->expects(self::never())
            ->method('saveLastPremiumProviderResponse');

        $mockLogger->expects(self::once())
            ->method('debug')
            ->with('Starting UpdateNameServers Job for domain [{domain.name}]. attempt {job.attempt}/{job.max_attempts}');

        $subscriptionRepo->expects(self::once())
            ->method('getNotAdministrativelyEndedOrSuspendedDnsSubscription')
            ->willReturn($dnsSubscription);

        $mockEventDispatcher->expects(self::never())
            ->method('dispatch');

        $updateNsJob = new UpdateNameservers($testDomain, true);
        $updateNsJob->handle(
            gandiClient: $mockGandiClient,
            updateNameserverAndSoaAction: $updateNsActionMock,
            logger: $mockLogger,
            subscriptionRepository: $subscriptionRepo,
            domainDeploymentRepository: $mockDomainDeploymentRepository,
            dnsDeploymentRepository: $mockDnsDeploymentRepository,
            dnsService: $mockDnsService,
            eventDispatcher: $mockEventDispatcher,
        );

        self::assertSame(TechnicalStatus::PENDING->value, $dnsSubscription->refresh()->technical_status);
    }
}
