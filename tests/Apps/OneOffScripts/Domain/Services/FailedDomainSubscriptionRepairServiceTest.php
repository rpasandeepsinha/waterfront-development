<?php

declare(strict_types=1);

namespace Tests\Apps\OneOffScripts\Domain\Services;

use DateTime;
use Exception;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\Enum\ProcessStatusEnum;
use RealtimeRegister\Domain\Process;
use RealtimeRegister\Domain\ProcessCollection;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\OneOffScripts\Domain\Enum\FailedDomainSubscriptionRepairPath;
use Waterfront\Apps\OneOffScripts\Domain\Services\FailedDomainSubscriptionRepairService;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Factories\NameserverAssignerFactory;
use Waterfront\Domain\DNS\Interfaces\NameserverAssignerInterface;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Domains\DTO\DomainDetailsDTO;
use Waterfront\Domain\Domains\Exceptions\DomainDoesNotExistException;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\RtrClient\Services\Enums\DomainStatus as RtrDomainStatus;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

#[CoversClass(FailedDomainSubscriptionRepairService::class)]
#[AllowMockObjectsWithoutExpectations]
class FailedDomainSubscriptionRepairServiceTest extends IntegrationTestCase
{
    private const string SOURCE = 'fix-failed-domain-subscriptions';

    private MockObject&DnsDeploymentRepository $dnsDeploymentRepository;

    private MockObject&NameserverAssignerFactory $nameserverAssignerFactory;

    private MockObject&LoggerInterface $logger;

    private MockObject&RtrService $rtrService;

    private DomainDeployment $domainDeployment;

    private FailedDomainSubscriptionRepairService $failedDomainSubscriptionRepairService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dnsDeploymentRepository = self::createMock(DnsDeploymentRepository::class);
        $this->nameserverAssignerFactory = self::createMock(NameserverAssignerFactory::class);
        $this->logger = self::createMock(LoggerInterface::class);
        $this->rtrService = self::createMock(RtrService::class);

        $this->failedDomainSubscriptionRepairService = new FailedDomainSubscriptionRepairService(
            dnsDeploymentRepository: $this->dnsDeploymentRepository,
            nameserverAssignerFactory: $this->nameserverAssignerFactory,
            logger: $this->logger,
            rtrService: $this->rtrService,
        );

        $product = ProductFactory::new()
            ->nlDomain()
            ->createOne();

        $this->domainDeployment = DomainDeploymentFactory::new()
            ->withSubscription($product)
            ->withRtrProvider()
            ->createOne();

        $this->domainDeployment->subscription->setRelation('domainDeployment', $this->domainDeployment);
        $this->domainDeployment->setRelation('subscription', $this->domainDeployment->subscription);
    }

    #[Test]
    public function determineRepairReturnsSkipWhenDnsDeploymentIsMissing(): void
    {
        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getDnsDeploymentFromDomainDeployment')
            ->with($this->domainDeployment)
            ->willReturn(null);

        $this->rtrService
            ->expects(self::never())
            ->method('fetchDomain');

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Skipping failed domain subscription because no DNS deployment was found.',
                self::callback(fn (array $context): bool => $this->assertBaseLogContext($context, $this->domainDeployment)
                    && $context[LoggingContextKeys::META]['reason'] === 'missing_dns_deployment'),
            );

        $repair = $this->failedDomainSubscriptionRepairService->determineRepair(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );

        self::assertSame(FailedDomainSubscriptionRepairPath::SKIP, $repair->path);
        self::assertSame('missing_dns_deployment', $repair->reason);
    }

    #[Test]
    public function determineRepairReturnsRetryProvisioningWhenRemoteDomainDoesNotExist(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $dnsDeployment = DnsDeploymentFactory::new()
            ->withExternalNameserver()
            ->makeOne();

        $this->dnsDeploymentRepository
            ->method('getDnsDeploymentFromDomainDeployment')
            ->willReturn($dnsDeployment);

        $this->rtrService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with($domain)
            ->willThrowException(new DomainDoesNotExistException());

        $repair = $this->failedDomainSubscriptionRepairService->determineRepair(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );

        self::assertSame(FailedDomainSubscriptionRepairPath::RETRY_PROVISIONING, $repair->path);
        self::assertSame('remote_domain_missing', $repair->reason);
    }

    #[Test]
    public function determineRepairReturnsSyncStatusOnlyWhenRemoteStatusIsOk(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $dnsDeployment = DnsDeploymentFactory::new()
            ->withExternalNameserver()
            ->makeOne();

        $this->dnsDeploymentRepository
            ->method('getDnsDeploymentFromDomainDeployment')
            ->willReturn($dnsDeployment);

        $this->rtrService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with($domain)
            ->willReturn($this->makeRemoteDomainDto(
                domainName: $domain,
                statuses: [RtrDomainStatus::OK->value],
                nameservers: ['ns1.example.test'],
            ));

        $repair = $this->failedDomainSubscriptionRepairService->determineRepair(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );

        self::assertSame(FailedDomainSubscriptionRepairPath::SYNC_STATUS_ONLY, $repair->path);
        self::assertSame('remote_status_is_ok', $repair->reason);
    }

    #[Test]
    public function determineRepairReturnsRepairMissingRemoteNameserversWhenRemoteNameserversAreMissingAndStoredNameserversExist(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $dnsDeployment = DnsDeploymentFactory::new()
            ->withExternalNameserver()
            ->makeOne();

        $this->dnsDeploymentRepository
            ->method('getDnsDeploymentFromDomainDeployment')
            ->willReturn($dnsDeployment);

        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameserverHostnames')
            ->with($dnsDeployment)
            ->willReturn(['ns1.example.test', 'ns2.example.test']);

        $this->rtrService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with($domain)
            ->willReturn($this->makeRemoteDomainDto(
                domainName: $domain,
                statuses: [RtrDomainStatus::INACTIVE->value],
                nameservers: [],
            ));

        $repair = $this->failedDomainSubscriptionRepairService->determineRepair(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );

        self::assertSame(FailedDomainSubscriptionRepairPath::REPAIR_NAMESERVERS, $repair->path);
        self::assertSame('remote_status_not_healthy_and_no_nameservers_set', $repair->reason);
    }

    #[Test]
    public function determineRepairReturnsRepairRemoteNameservers(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $dnsDeployment = DnsDeploymentFactory::new()
            ->for($this->domainDeployment->subscription)
            ->withInternalNameserver()
            ->createOne();

        $this->dnsDeploymentRepository
            ->method('getDnsDeploymentFromDomainDeployment')
            ->willReturn($dnsDeployment);

        $storedNameservers = $dnsDeployment->dnsNameservers->pluck('nameserver')->toArray();

        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameserverHostnames')
            ->willReturn($storedNameservers);

        $this->rtrService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with($domain)
            ->willReturn($this->makeRemoteDomainDto(
                domainName: $domain,
                statuses: [RtrDomainStatus::INACTIVE->value],
                nameservers: [],
            ));

        $repair = $this->failedDomainSubscriptionRepairService->determineRepair(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );

        self::assertSame(FailedDomainSubscriptionRepairPath::REPAIR_NAMESERVERS, $repair->path);
    }

    #[Test]
    public function determineRepairDoesNotReturnsRepairRemoteNameserversWhenNameserversAreEqual(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $dnsDeployment = DnsDeploymentFactory::new()
            ->for($this->domainDeployment->subscription)
            ->withInternalNameserver()
            ->createOne();

        $this->dnsDeploymentRepository
            ->method('getDnsDeploymentFromDomainDeployment')
            ->willReturn($dnsDeployment);

        $nameservers = [
            'ns1.example.test',
            'ns2.example.test',
            'ns3.example.test',
        ];

        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameserverHostnames')
            ->willReturn($nameservers);

        $this->rtrService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with($domain)
            ->willReturn($this->makeRemoteDomainDto(
                domainName: $domain,
                statuses: [RtrDomainStatus::INACTIVE->value],
                nameservers: $nameservers,
            ));

        $repair = $this->failedDomainSubscriptionRepairService->determineRepair(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );

        self::assertNotSame(FailedDomainSubscriptionRepairPath::REPAIR_NAMESERVERS, $repair->path);
    }

    #[Test]
    public function determineRepairReturnsRestoreOpenProcessesExists(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $dnsDeployment = DnsDeploymentFactory::new()
            ->withExternalNameserver()
            ->makeOne();

        $this->dnsDeploymentRepository
            ->method('getDnsDeploymentFromDomainDeployment')
            ->willReturn($dnsDeployment);

        $this->rtrService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with($domain)
            ->willThrowException(new Exception('Not found.'));

        $this->rtrService
            ->expects(self::once())
            ->method('listProcessesForDomain')
            ->with($domain)
            ->willReturn($this->createProcessCollection([
                $this->createProcess(
                    processId: 9001,
                    status: ProcessStatusEnum::STATUS_RUNNING,
                    type: 'domain',
                    createdDate: '2026-03-20T12:00:00Z',
                ),
            ]));

        $repair = $this->failedDomainSubscriptionRepairService->determineRepair(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );

        self::assertSame(FailedDomainSubscriptionRepairPath::RESTORE_FROM_PROCESS, $repair->path);
        self::assertSame('remote_processes_found_no_domain', $repair->reason);
        self::assertNotNull($repair->processCollection);
        self::assertNotNull($repair->processCollection[0]);
        self::assertSame(9001, $repair->processCollection[0]->id);
        self::assertSame(ProcessStatusEnum::STATUS_RUNNING, $repair->processCollection[0]->status);
    }

    #[Test]
    public function determineRepairReturnsSyncStatusOnlyWhenRemoteExistsButNoRepairPathApplies(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $dnsDeployment = DnsDeploymentFactory::new()
            ->withExternalNameserver()
            ->makeOne();

        $this->dnsDeploymentRepository
            ->method('getDnsDeploymentFromDomainDeployment')
            ->willReturn($dnsDeployment);

        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameserverHostnames')
            ->with($dnsDeployment)
            ->willReturn(['ns1.example.test']);

        $this->rtrService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with($domain)
            ->willReturn($this->makeRemoteDomainDto(
                domainName: $domain,
                statuses: [RtrDomainStatus::INACTIVE->value],
                nameservers: ['ns1.example.test'],
            ));

        $repair = $this->failedDomainSubscriptionRepairService->determineRepair(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );

        self::assertSame(FailedDomainSubscriptionRepairPath::SYNC_STATUS_ONLY, $repair->path);
        self::assertSame(
            'remote_exists_but_no_nameserver_repair_or_prevalidation_repair_applicable',
            $repair->reason,
        );
    }

    #[Test]
    public function determineRepairReturnsRestoreFromProcessWhenRemoteDomainExistsAndOpenProcessIsFound(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $dnsDeployment = DnsDeploymentFactory::new()
            ->withExternalNameserver()
            ->makeOne();

        $this->dnsDeploymentRepository
            ->method('getDnsDeploymentFromDomainDeployment')
            ->willReturn($dnsDeployment);

        $openProcessCollection = $this->createProcessCollection([
            $this->createProcess(
                processId: 8123,
                status: ProcessStatusEnum::STATUS_RUNNING,
                type: 'domain',
                createdDate: '2026-03-23T10:00:00Z',
            ),
        ]);

        $this->rtrService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with($domain)
            ->willReturn($this->makeRemoteDomainDto(
                domainName: $domain,
                statuses: [RtrDomainStatus::INACTIVE->value],
                nameservers: ['ns1.example.test'],
            ));

        $this->rtrService
            ->expects(self::once())
            ->method('listProcessesForDomain')
            ->with($domain)
            ->willReturn($openProcessCollection);

        $repair = $this->failedDomainSubscriptionRepairService->determineRepair(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );

        self::assertSame(FailedDomainSubscriptionRepairPath::RESTORE_FROM_PROCESS, $repair->path);
        self::assertSame('remote_processes_found_no_domain', $repair->reason);
        self::assertNotNull($repair->processCollection);
        self::assertSame(8123, $repair->processCollection[0]?->id);
    }

    #[Test]
    public function determineRepairDoesNotChooseNameserverRepairWhenRemoteNameserversAreAlreadyPresent(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $dnsDeployment = DnsDeploymentFactory::new()
            ->withExternalNameserver()
            ->makeOne();

        $this->dnsDeploymentRepository
            ->method('getDnsDeploymentFromDomainDeployment')
            ->willReturn($dnsDeployment);

        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameserverHostnames')
            ->willReturn(['ns1.remote.test']);

        $this->rtrService
            ->expects(self::once())
            ->method('fetchDomain')
            ->willReturn($this->makeRemoteDomainDto(
                domainName: $domain,
                statuses: [RtrDomainStatus::INACTIVE->value],
                nameservers: ['ns1.remote.test'],
            ));

        $this->rtrService
            ->expects(self::once())
            ->method('listProcessesForDomain')
            ->with($domain)
            ->willReturn($this->createProcessCollection([]));

        $repair = $this->failedDomainSubscriptionRepairService->determineRepair(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );

        self::assertNotSame(FailedDomainSubscriptionRepairPath::REPAIR_NAMESERVERS, $repair->path);
        self::assertSame(FailedDomainSubscriptionRepairPath::SYNC_STATUS_ONLY, $repair->path);
    }

    #[Test]
    public function determineRepairReturnsSyncStatusOnlyWhenRemoteAndStoredNameserversMatch(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $dnsDeployment = DnsDeploymentFactory::new()
            ->withExternalNameserver()
            ->makeOne();

        $this->dnsDeploymentRepository
            ->method('getDnsDeploymentFromDomainDeployment')
            ->willReturn($dnsDeployment);

        $this->dnsDeploymentRepository
            ->method('getNameserverHostnames')
            ->willReturn(['ns1.example.test']);

        $this->rtrService
            ->expects(self::once())
            ->method('fetchDomain')
            ->willReturn($this->makeRemoteDomainDto(
                domainName: $domain,
                statuses: [RtrDomainStatus::INACTIVE->value],
                nameservers: ['ns1.example.test'],
            ));

        $this->rtrService
            ->expects(self::once())
            ->method('listProcessesForDomain')
            ->with($domain)
            ->willReturn($this->createProcessCollection([]));

        $repair = $this->failedDomainSubscriptionRepairService->determineRepair(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );

        self::assertSame(FailedDomainSubscriptionRepairPath::SYNC_STATUS_ONLY, $repair->path);
    }

    #[Test]
    public function determineRepairLogsFetchedRemoteDomainMetadata(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $dnsDeployment = DnsDeploymentFactory::new()
            ->withExternalNameserver()
            ->makeOne();

        $this->dnsDeploymentRepository
            ->method('getDnsDeploymentFromDomainDeployment')
            ->willReturn($dnsDeployment);

        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameserverHostnames')
            ->with($dnsDeployment)
            ->willReturn(['ns1.example.test']);

        $this->rtrService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with($domain)
            ->willReturn($this->makeRemoteDomainDto(
                domainName: $domain,
                statuses: [RtrDomainStatus::INACTIVE->value],
                nameservers: ['ns1.remote.test'],
            ));

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Fetched remote domain for failed subscription.',
                self::callback(fn (array $context): bool => $this->assertBaseLogContext($context, $this->domainDeployment)
                    && $context[LoggingContextKeys::META]['remote_statuses'] === [RtrDomainStatus::INACTIVE->value]
                    && $context[LoggingContextKeys::META]['remote_nameservers'] === ['ns1.remote.test']),
            );

        $this->failedDomainSubscriptionRepairService->determineRepair(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );
    }

    #[Test]
    public function repairMissingRemoteNameserversLogsWarningAndReturnsWhenDnsDeploymentIsMissing(): void
    {
        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getDnsDeploymentFromDomainDeployment')
            ->with($this->domainDeployment)
            ->willReturn(null);

        $this->nameserverAssignerFactory
            ->expects(self::never())
            ->method('createAssigner');

        $this->rtrService
            ->expects(self::never())
            ->method('fetchDomain');

        $this->rtrService
            ->expects(self::never())
            ->method('updateNameServers');

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Skipping nameserver repair because no DNS deployment was found.',
                self::callback(fn (array $context): bool => $this->assertBaseLogContext($context, $this->domainDeployment)
                    && $context[LoggingContextKeys::META]['reason'] === 'missing_dns_deployment'),
            );

        $originalTechnicalStatus = $this->domainDeployment->subscription->technical_status;
        $originalLastResult = $this->domainDeployment->last_result;
        $originalLastResultReceived = $this->domainDeployment->last_result_received;

        $this->failedDomainSubscriptionRepairService->repairMissingRemoteNameservers(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );

        $this->domainDeployment->subscription->refresh();
        $this->domainDeployment->refresh();

        self::assertSame($originalTechnicalStatus, $this->domainDeployment->subscription->technical_status);
        self::assertSame($originalLastResult, $this->domainDeployment->last_result);
        self::assertEquals($originalLastResultReceived, $this->domainDeployment->last_result_received);
    }

    #[Test]
    public function repairMissingRemoteNameserversLogsWarningAndReturnsWhenRemoteDomainIsMissing(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $dnsDeployment = DnsDeploymentFactory::new()
            ->withExternalNameserver()
            ->makeOne();

        $this->dnsDeploymentRepository
            ->method('getDnsDeploymentFromDomainDeployment')
            ->willReturn($dnsDeployment);

        $this->rtrService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with($domain)
            ->willThrowException(new DomainDoesNotExistException());

        $this->nameserverAssignerFactory
            ->expects(self::never())
            ->method('createAssigner');

        $this->rtrService
            ->expects(self::never())
            ->method('updateNameServers');

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Skipping nameserver repair because the remote domain no longer exists at RTR.',
                self::callback(fn (array $context): bool => $this->assertBaseLogContext($context, $this->domainDeployment)
                    && $context[LoggingContextKeys::META]['reason'] === 'remote_domain_missing'),
            );

        $this->failedDomainSubscriptionRepairService->repairMissingRemoteNameservers(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );
    }

    #[Test]
    public function repairMissingRemoteNameserversSyncsStatusWhenRemoteAlreadyHasNameservers(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $dnsDeployment = DnsDeploymentFactory::new()
            ->withExternalNameserver()
            ->makeOne();

        $remoteDomain = $this->makeRemoteDomainDto(
            domainName: $domain,
            statuses: [RtrDomainStatus::OK->value],
            nameservers: ['ns1.remote.test'],
            expiryDate: new DateTime('2026-12-31 10:00:00'),
        );

        $storedNameservers = [new Nameserver(hostname: 'ns1.remote.test')];

        $this->dnsDeploymentRepository
            ->method('getDnsDeploymentFromDomainDeployment')
            ->willReturn($dnsDeployment);

        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameserverHostnames')
            ->with($dnsDeployment)
            ->willReturn(['ns1.remote.test']);

        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameservers')
            ->with($dnsDeployment)
            ->willReturn($storedNameservers);

        $this->rtrService
            ->expects(self::exactly(2))
            ->method('fetchDomain')
            ->with($domain)
            ->willReturn($remoteDomain);

        $this->rtrService
            ->expects(self::once())
            ->method('updateNameServers')
            ->with($domain, $storedNameservers);

        $this->rtrService
            ->expects(self::once())
            ->method('getTechnicalStatusFromDomainStatusList')
            ->with([RtrDomainStatus::OK->value])
            ->willReturn(TechnicalStatus::OK->value);

        $this->nameserverAssignerFactory
            ->expects(self::never())
            ->method('createAssigner');

        $this->failedDomainSubscriptionRepairService->repairMissingRemoteNameservers(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );

        $this->domainDeployment->subscription->refresh();
        $this->domainDeployment->refresh();

        self::assertSame(TechnicalStatus::OK->value, $this->domainDeployment->subscription->technical_status);
        $this->assertDeploymentLastResultContains(
            $this->domainDeployment,
            [
                'repair' => 'synced_status_from_remote_domain',
                'source' => self::SOURCE,
            ],
        );
    }

    #[Test]
    public function repairMissingRemoteNameserversRestoresInternalNameserversBeforeRemoteRepair(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $dnsDeployment = DnsDeploymentFactory::new()
            ->withInternalNameserver()
            ->makeOne();

        $remoteBeforeRepair = $this->makeRemoteDomainDto(
            domainName: $domain,
            statuses: [RtrDomainStatus::INACTIVE->value],
            nameservers: [],
        );

        $remoteAfterRepair = $this->makeRemoteDomainDto(
            domainName: $domain,
            statuses: [RtrDomainStatus::OK->value],
            nameservers: ['ns1.example.test', 'ns2.example.test'],
        );

        $storedNameservers = [
            new Nameserver(hostname:  'ns1.example.test'),
            new Nameserver(hostname:  'ns2.example.test'),
        ];

        $this->dnsDeploymentRepository
            ->method('getDnsDeploymentFromDomainDeployment')
            ->willReturn($dnsDeployment);

        $this->rtrService
            ->expects(self::exactly(2))
            ->method('fetchDomain')
            ->with($domain)
            ->willReturnOnConsecutiveCalls($remoteBeforeRepair, $remoteAfterRepair);

        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameserverHostnames')
            ->with($dnsDeployment)
            ->willReturn([]);

        $mockAssigner = self::createMock(NameserverAssignerInterface::class);
        $mockAssigner->expects(self::once())
            ->method('assign')
            ->with($dnsDeployment)
            ->willReturn($storedNameservers);

        $this->nameserverAssignerFactory
            ->expects(self::once())
            ->method('createAssigner')
            ->with(NameserverType::INTERNAL)
            ->willReturn($mockAssigner);

        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameservers')
            ->with($dnsDeployment)
            ->willReturn($storedNameservers);

        $this->rtrService
            ->expects(self::once())
            ->method('updateNameServers')
            ->with($domain, $storedNameservers);

        $this->rtrService
            ->expects(self::once())
            ->method('getTechnicalStatusFromDomainStatusList')
            ->with([RtrDomainStatus::OK->value])
            ->willReturn(TechnicalStatus::OK->value);

        $this->failedDomainSubscriptionRepairService->repairMissingRemoteNameservers(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );

        $this->domainDeployment->subscription->refresh();
        $this->domainDeployment->refresh();

        self::assertSame(TechnicalStatus::OK->value, $this->domainDeployment->subscription->technical_status);
        $this->assertDeploymentLastResultContains(
            $this->domainDeployment,
            [
                'repair' => 'synced_status_from_remote_domain',
                'source' => self::SOURCE,
            ],
        );
    }

    #[Test]
    public function repairMissingRemoteNameserversLogsWarningWhenStoredNameserversRemainMissingAfterInternalRestore(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $dnsDeployment = DnsDeploymentFactory::new()
            ->withInternalNameserver()
            ->makeOne();

        $originalTechnicalStatus = $this->domainDeployment->subscription->technical_status;
        $originalLastResult = $this->domainDeployment->last_result;
        $originalLastResultReceived = $this->domainDeployment->last_result_received;

        $this->dnsDeploymentRepository
            ->method('getDnsDeploymentFromDomainDeployment')
            ->willReturn($dnsDeployment);

        $this->rtrService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with($domain)
            ->willReturn($this->makeRemoteDomainDto(
                domainName: $domain,
                statuses: [RtrDomainStatus::INACTIVE->value],
                nameservers: [],
            ));

        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameserverHostnames')
            ->with($dnsDeployment)->willReturn([]);

        $mockAssigner = self::createMock(NameserverAssignerInterface::class);
        $mockAssigner->expects(self::once())
            ->method('assign')
            ->with($dnsDeployment)
            ->willReturn([]);

        $this->nameserverAssignerFactory
            ->expects(self::once())
            ->method('createAssigner')
            ->with(NameserverType::INTERNAL)
            ->willReturn($mockAssigner);

        $this->rtrService
            ->expects(self::never())
            ->method('updateNameServers');

        $this->logger
            ->expects(self::exactly(2))
            ->method('warning');

        $this->failedDomainSubscriptionRepairService->repairMissingRemoteNameservers(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );

        $this->domainDeployment->subscription->refresh();
        $this->domainDeployment->refresh();

        self::assertSame($originalTechnicalStatus, $this->domainDeployment->subscription->technical_status);
        self::assertSame($originalLastResult, $this->domainDeployment->last_result);
        self::assertEquals($originalLastResultReceived, $this->domainDeployment->last_result_received);
    }

    #[Test]
    public function repairMissingRemoteNameserversLogsWarningWhenAssignerReturnsNoNameservers(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $dnsDeployment = DnsDeploymentFactory::new()
            ->withExternalNameserver()
            ->makeOne();

        $this->dnsDeploymentRepository
            ->method('getDnsDeploymentFromDomainDeployment')
            ->willReturn($dnsDeployment);

        $this->rtrService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with($domain)
            ->willReturn($this->makeRemoteDomainDto(
                domainName: $domain,
                statuses: [RtrDomainStatus::INACTIVE->value],
                nameservers: [],
            ));

        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameserverHostnames')
            ->with($dnsDeployment)
            ->willReturn([]);

        $mockAssigner = self::createMock(NameserverAssignerInterface::class);
        $mockAssigner->expects(self::once())
            ->method('assign')
            ->with($dnsDeployment)
            ->willReturn([]);

        $this->nameserverAssignerFactory
            ->expects(self::once())
            ->method('createAssigner')
            ->with($dnsDeployment->nameserver_type)
            ->willReturn($mockAssigner);

        $this->rtrService
            ->expects(self::never())
            ->method('updateNameServers');

        $matcher = self::exactly(2);
        $this->logger
            ->expects($matcher)
            ->method('warning')
            ->willReturnCallback(function (string $message, array $context) use ($matcher): void {
                if ($matcher->numberOfInvocations() === 2) {
                    self::assertSame(
                        'Skipping nameserver repair because RTR has no nameservers and no stored nameservers could be assigned.',
                        $message,
                    );
                    self::assertSame('missing_stored_nameservers', $context[LoggingContextKeys::META]['reason']);
                }
            });

        $this->failedDomainSubscriptionRepairService->repairMissingRemoteNameservers(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );
    }

    #[Test]
    public function repairMissingRemoteNameserversUpdatesRemoteNameserversRefetchesAndSyncsLocalStatus(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $dnsDeployment = DnsDeploymentFactory::new()
            ->withExternalNameserver()
            ->makeOne();

        $remoteBeforeRepair = $this->makeRemoteDomainDto(
            domainName: $domain,
            statuses: [RtrDomainStatus::INACTIVE->value],
            nameservers: [],
        );

        $remoteAfterRepair = $this->makeRemoteDomainDto(
            domainName: $domain,
            statuses: [RtrDomainStatus::OK->value],
            nameservers: ['ns1.example.test'],
        );

        $storedNameservers = [['hostname' => 'ns1.example.test']];

        $this->dnsDeploymentRepository
            ->method('getDnsDeploymentFromDomainDeployment')
            ->willReturn($dnsDeployment);

        $this->rtrService
            ->expects(self::exactly(2))
            ->method('fetchDomain')
            ->with($domain)
            ->willReturnOnConsecutiveCalls($remoteBeforeRepair, $remoteAfterRepair);

        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameserverHostnames')
            ->with($dnsDeployment)
            ->willReturn(['ns1.example.test']);

        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameservers')
            ->with($dnsDeployment)
            ->willReturn($storedNameservers);

        $this->rtrService
            ->expects(self::once())
            ->method('updateNameServers')
            ->with($domain, $storedNameservers);

        $this->rtrService
            ->expects(self::once())
            ->method('getTechnicalStatusFromDomainStatusList')
            ->with([RtrDomainStatus::OK->value])
            ->willReturn(TechnicalStatus::OK->value);

        $this->failedDomainSubscriptionRepairService->repairMissingRemoteNameservers(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );

        $this->domainDeployment->subscription->refresh();
        $this->domainDeployment->refresh();

        self::assertSame(TechnicalStatus::OK->value, $this->domainDeployment->subscription->technical_status);
        $this->assertDeploymentLastResultContains(
            $this->domainDeployment,
            [
                'repair' => 'synced_status_from_remote_domain',
                'source' => self::SOURCE,
            ],
        );
    }

    #[Test]
    public function repairMissingRemoteNameserversSyncsStatusAfterRefetchEvenWhenRemoteStillHasNoNameservers(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $dnsDeployment = DnsDeploymentFactory::new()
            ->withExternalNameserver()
            ->makeOne();

        $remoteBeforeRepair = $this->makeRemoteDomainDto(
            domainName: $domain,
            statuses: [RtrDomainStatus::INACTIVE->value],
            nameservers: [],
        );

        $remoteAfterRepair = $this->makeRemoteDomainDto(
            domainName: $domain,
            statuses: [RtrDomainStatus::INACTIVE->value],
            nameservers: [],
        );

        $storedNameservers = [['hostname' => 'ns1.example.test']];

        $this->dnsDeploymentRepository
            ->method('getDnsDeploymentFromDomainDeployment')
            ->willReturn($dnsDeployment);

        $this->rtrService
            ->expects(self::exactly(2))
            ->method('fetchDomain')
            ->with($domain)
            ->willReturnOnConsecutiveCalls($remoteBeforeRepair, $remoteAfterRepair);

        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameserverHostnames')
            ->with($dnsDeployment)
            ->willReturn(['ns1.example.test']);

        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameservers')
            ->with($dnsDeployment)
            ->willReturn($storedNameservers);

        $this->rtrService
            ->expects(self::once())
            ->method('updateNameServers')
            ->with($domain, $storedNameservers);

        $this->rtrService
            ->expects(self::once())
            ->method('getTechnicalStatusFromDomainStatusList')
            ->with([RtrDomainStatus::INACTIVE->value])
            ->willReturn(TechnicalStatus::FAILED->value);

        $this->failedDomainSubscriptionRepairService->repairMissingRemoteNameservers(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );

        $this->domainDeployment->subscription->refresh();
        $this->domainDeployment->refresh();

        self::assertSame(TechnicalStatus::FAILED->value, $this->domainDeployment->subscription->technical_status);
        $this->assertDeploymentLastResultContains(
            $this->domainDeployment,
            [
                'repair' => 'synced_status_from_remote_domain',
                'source' => self::SOURCE,
            ],
        );
    }

    #[Test]
    public function restoreFromProcessesLogsWarningAndReturnsWhenNoCurrentOpenProcessExists(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $originalTechnicalStatus = $this->domainDeployment->subscription->technical_status;
        $originalLastResult = $this->domainDeployment->last_result;
        $originalLastResultReceived = $this->domainDeployment->last_result_received;

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Skipping pending-state restore because RTR no longer has an open prevalidation process.',
                self::callback(fn (array $context): bool => $this->assertBaseLogContext($context, $this->domainDeployment)
                    && $context[LoggingContextKeys::META]['reason'] === 'open_prevalidation_process_no_longer_present'),
            );

        $processCollection = ProcessCollection::fromArray([[
            'id' => 1001,
            'user' => 'sandwave',
            'customer' => 'sandwave',
            'status' => ProcessStatusEnum::STATUS_CANCELLED,
            'createdDate' => '2020-03-04 12:34:56',
            'updatedDate' => '2021-03-04 12:34:56',
            'startedDate' => '2021-03-04 12:34:56',
            'type' => 'domain',
            'identifier' => 'example.nl',
            'action' => 'update',
            'command' => [],
        ]]);

        $this->failedDomainSubscriptionRepairService->restoreFromProcesses(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
            processCollection: $processCollection
        );

        $this->domainDeployment->subscription->refresh();
        $this->domainDeployment->refresh();

        self::assertSame($originalTechnicalStatus, $this->domainDeployment->subscription->technical_status);
        self::assertSame($originalLastResult, $this->domainDeployment->last_result);
        self::assertEquals($originalLastResultReceived, $this->domainDeployment->last_result_received);
    }

    #[Test]
    public function restorePendingFromOpenPrevalidationProcessRestoresPendingAndRecordsResult(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $this->logger
            ->expects(self::once())
            ->method('info');

        $process = $this->createProcess(
            processId: 1001,
            status: ProcessStatusEnum::STATUS_VALIDATED,
            type: 'domain',
            createdDate: '2026-03-20T12:00:00Z',
        );

        $this->failedDomainSubscriptionRepairService->restoreFromProcesses(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
            processCollection: $this->createProcessCollection([$process])
        );

        $this->domainDeployment->subscription->refresh();
        $this->domainDeployment->refresh();

        self::assertSame(TechnicalStatus::PENDING->value, $this->domainDeployment->subscription->technical_status);
        self::assertSame(RtrDomainStatus::PENDING_VALIDATION, $this->domainDeployment->domain_status);
        $this->assertDeploymentLastResultContains(
            $this->domainDeployment,
            [
                'repair' => 'restored_pending_from_open_prevalidation_process',
                'source' => self::SOURCE,
                'current_rtr_process_id' => 1001,
                'current_rtr_process_status' => ProcessStatusEnum::STATUS_VALIDATED,
            ],
        );
    }

    #[Test]
    public function restoreFromProcessesPicksLatestOpenDomainProcessFromCollection(): void
    {
        $olderOpenDomainProcess = $this->createProcess(
            processId: 1001,
            status: ProcessStatusEnum::STATUS_VALIDATED,
            type: 'domain',
            createdDate: '2026-03-20T12:00:00Z',
        );

        $latestOpenDomainProcess = $this->createProcess(
            processId: 3003,
            status: ProcessStatusEnum::STATUS_RUNNING,
            type: 'domain',
            createdDate: '2026-03-22T12:00:00Z',
        );

        $this->failedDomainSubscriptionRepairService->restoreFromProcesses(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
            processCollection: $this->createProcessCollection([
                $olderOpenDomainProcess,
                $latestOpenDomainProcess,
            ]),
        );

        $this->domainDeployment->refresh();
        $this->assertDeploymentLastResultContains(
            $this->domainDeployment,
            [
                'current_rtr_process_id' => 3003,
                'current_rtr_process_status' => ProcessStatusEnum::STATUS_RUNNING,
            ],
        );
    }

    #[Test]
    public function syncStatusFromRemoteLogsWarningAndReturnsWhenRemoteDomainIsMissing(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $originalTechnicalStatus = $this->domainDeployment->subscription->technical_status;
        $originalLastResult = $this->domainDeployment->last_result;
        $originalLastResultReceived = $this->domainDeployment->last_result_received;

        $this->rtrService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with($domain)
            ->willThrowException(new DomainDoesNotExistException());

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Skipping status sync because the remote domain no longer exists at RTR.',
                self::callback(fn (array $context): bool => $this->assertBaseLogContext($context, $this->domainDeployment)
                    && $context[LoggingContextKeys::META]['reason'] === 'remote_domain_missing'),
            );

        $this->failedDomainSubscriptionRepairService->syncStatusFromRemote(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );

        $this->domainDeployment->subscription->refresh();
        $this->domainDeployment->refresh();

        self::assertSame($originalTechnicalStatus, $this->domainDeployment->subscription->technical_status);
        self::assertSame($originalLastResult, $this->domainDeployment->last_result);
        self::assertEquals($originalLastResultReceived, $this->domainDeployment->last_result_received);
    }

    #[Test]
    public function syncStatusFromRemoteUpdatesSubscriptionAndRecordsResultWhenSyncIsAllowed(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $remoteDomain = $this->makeRemoteDomainDto(
            domainName: $domain,
            statuses: [RtrDomainStatus::OK->value],
            nameservers: ['ns1.example.test'],
            expiryDate: new DateTime('2026-12-31 10:00:00'),
        );

        $this->rtrService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with($domain)
            ->willReturn($remoteDomain);

        $this->rtrService
            ->expects(self::once())
            ->method('getTechnicalStatusFromDomainStatusList')
            ->with([RtrDomainStatus::OK->value])
            ->willReturn(TechnicalStatus::OK->value);

        $this->rtrService
            ->expects(self::once())
            ->method('getPrimaryDomainStatusFromDomainStatusList')
            ->with([RtrDomainStatus::OK->value])
            ->willReturn(RtrDomainStatus::OK);

        $this->logger
            ->expects(self::exactly(2))
            ->method('info');

        $this->failedDomainSubscriptionRepairService->syncStatusFromRemote(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );

        $this->domainDeployment->subscription->refresh();
        $this->domainDeployment->refresh();

        self::assertSame(TechnicalStatus::OK->value, $this->domainDeployment->subscription->technical_status);
        self::assertSame(RtrDomainStatus::OK, $this->domainDeployment->domain_status);
        $this->assertDeploymentLastResultContains(
            $this->domainDeployment,
            [
                'repair' => 'synced_status_from_remote_domain',
                'source' => self::SOURCE,
            ],
        );
    }

    #[Test]
    public function findOpenPrevalidationProcessForDomainReturnsNullWhenNoProcessesExist(): void
    {
        self::assertNull(
            $this->failedDomainSubscriptionRepairService->findLatestOpenDomainProcess($this->createProcessCollection([])),
        );
    }

    #[Test]
    public function findOpenPrevalidationProcessForDomainReturnsLatestOpenDomainProcess(): void
    {
        $latestOpenDomainProcess = $this->createProcess(
            processId: 5005,
            status: ProcessStatusEnum::STATUS_RUNNING,
            type: 'domain',
            createdDate: '2026-03-23T12:00:00Z',
        );

        $olderOpenDomainProcess = $this->createProcess(
            processId: 4004,
            status: ProcessStatusEnum::STATUS_VALIDATED,
            type: 'domain',
            createdDate: '2026-03-22T12:00:00Z',
        );

        $closedDomainProcess = $this->createProcess(
            processId: 3003,
            status: ProcessStatusEnum::STATUS_COMPLETED,
            type: 'domain',
            createdDate: '2026-03-24T12:00:00Z',
        );

        $openNonDomainProcess = $this->createProcess(
            processId: 2002,
            status: ProcessStatusEnum::STATUS_RUNNING,
            type: 'contact',
            createdDate: '2026-03-25T12:00:00Z',
        );

        $processes = $this->createProcessCollection([
            $closedDomainProcess,
            $openNonDomainProcess,
            $olderOpenDomainProcess,
            $latestOpenDomainProcess,
        ]);

        $actual = $this->failedDomainSubscriptionRepairService->findLatestOpenDomainProcess($processes);

        self::assertInstanceOf(Process::class, $actual);
        self::assertSame(5005, $actual->id);
    }

    #[Test]
    public function findOpenPrevalidationProcessForDomainReturnsNullWhenOnlyClosedProcessesExist(): void
    {
        self::assertNull(
            $this->failedDomainSubscriptionRepairService->findLatestOpenDomainProcess($this->createProcessCollection([
                $this->createProcess(
                    processId: 6006,
                    status: ProcessStatusEnum::STATUS_COMPLETED,
                    type: 'domain',
                    createdDate: '2026-03-24T12:00:00Z',
                ),
            ])),
        );
    }

    #[Test]
    public function findOpenPrevalidationProcessForDomainReturnsNullWhenOnlyNonDomainOpenProcessesExist(): void
    {
        self::assertNull(
            $this->failedDomainSubscriptionRepairService->findLatestOpenDomainProcess($this->createProcessCollection([
                $this->createProcess(
                    processId: 7007,
                    status: ProcessStatusEnum::STATUS_RUNNING,
                    type: 'contact',
                    createdDate: '2026-03-24T12:00:00Z',
                ),
            ])),
        );
    }

    #[Test]
    public function determineRepairReturnsSkipWhenRtrLookupFailsWithUnexpectedException(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $dnsDeployment = DnsDeploymentFactory::new()
            ->withExternalNameserver()
            ->makeOne();

        $this->dnsDeploymentRepository
            ->method('getDnsDeploymentFromDomainDeployment')
            ->willReturn($dnsDeployment);

        $this->rtrService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with($domain)
            ->willThrowException(new Exception('RTR is on fire'));

        $this->rtrService
            ->expects(self::never())
            ->method('listProcessesForDomain');

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Skipping failed domain subscription because RTR domain lookup failed.',
                self::callback(fn (array $context): bool => $this->assertBaseLogContext($context, $this->domainDeployment)
                    && $context[LoggingContextKeys::META]['reason'] === 'rtr_domain_lookup_failed'),
            );

        $repair = $this->failedDomainSubscriptionRepairService->determineRepair(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );

        self::assertSame(FailedDomainSubscriptionRepairPath::SKIP, $repair->path);
        self::assertSame('rtr_domain_lookup_failed', $repair->reason);
    }

    #[Test]
    public function repairMissingRemoteNameserversSkipsWhenRtrLookupFailsWithUnexpectedException(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $dnsDeployment = DnsDeploymentFactory::new()
            ->withExternalNameserver()
            ->makeOne();

        $this->dnsDeploymentRepository
            ->method('getDnsDeploymentFromDomainDeployment')
            ->willReturn($dnsDeployment);

        $this->rtrService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with($domain)
            ->willThrowException(new Exception('RTR is on fire'));

        $this->rtrService
            ->expects(self::never())
            ->method('updateNameServers');

        $this->nameserverAssignerFactory
            ->expects(self::never())
            ->method('createAssigner');

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Skipping nameserver repair because RTR domain lookup failed.',
                self::callback(fn (array $context): bool => $this->assertBaseLogContext($context, $this->domainDeployment)
                    && $context[LoggingContextKeys::META]['reason'] === 'rtr_domain_lookup_failed'),
            );

        $this->failedDomainSubscriptionRepairService->repairMissingRemoteNameservers(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );
    }

    #[Test]
    public function repairMissingRemoteNameserversSkipsWhenUpdateNameServersFails(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $dnsDeployment = DnsDeploymentFactory::new()
            ->withExternalNameserver()
            ->makeOne();

        $remoteDomain = $this->makeRemoteDomainDto(
            domainName: $domain,
            statuses: [RtrDomainStatus::INACTIVE->value],
            nameservers: [],
        );

        $storedNameservers = [new Nameserver(hostname: 'ns1.example.test')];

        $this->dnsDeploymentRepository
            ->method('getDnsDeploymentFromDomainDeployment')
            ->willReturn($dnsDeployment);

        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameserverHostnames')
            ->with($dnsDeployment)
            ->willReturn(['ns1.example.test']);

        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameservers')
            ->with($dnsDeployment)
            ->willReturn($storedNameservers);

        $this->rtrService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with($domain)
            ->willReturn($remoteDomain);

        $this->rtrService
            ->expects(self::once())
            ->method('updateNameServers')
            ->with($domain, $storedNameservers)
            ->willThrowException(new Exception('RTR refused the update'));

        $this->rtrService
            ->expects(self::never())
            ->method('getTechnicalStatusFromDomainStatusList');

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Skipping nameserver repair follow-up because RTR nameserver update or refetch failed.',
                self::callback(fn (array $context): bool => $this->assertBaseLogContext($context, $this->domainDeployment)
                    && $context[LoggingContextKeys::META]['reason'] === 'rtr_nameserver_update_or_refetch_failed'),
            );

        $this->failedDomainSubscriptionRepairService->repairMissingRemoteNameservers(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );
    }

    #[Test]
    public function syncStatusFromRemoteSkipsWhenRtrLookupFailsWithUnexpectedException(): void
    {
        $domain = $this->domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain);

        $originalTechnicalStatus = $this->domainDeployment->subscription->technical_status;
        $originalLastResult = $this->domainDeployment->last_result;
        $originalLastResultReceived = $this->domainDeployment->last_result_received;

        $this->rtrService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with($domain)
            ->willThrowException(new Exception('RTR is on fire'));

        $this->rtrService
            ->expects(self::never())
            ->method('getTechnicalStatusFromDomainStatusList');

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Skipping status sync because RTR domain lookup failed.',
                self::callback(fn (array $context): bool => $this->assertBaseLogContext($context, $this->domainDeployment)
                    && $context[LoggingContextKeys::META]['reason'] === 'rtr_domain_lookup_failed'),
            );

        $this->failedDomainSubscriptionRepairService->syncStatusFromRemote(
            subscription: $this->domainDeployment->subscription,
            source: self::SOURCE,
        );

        $this->domainDeployment->subscription->refresh();
        $this->domainDeployment->refresh();

        self::assertSame($originalTechnicalStatus, $this->domainDeployment->subscription->technical_status);
        self::assertSame($originalLastResult, $this->domainDeployment->last_result);
        self::assertEquals($originalLastResultReceived, $this->domainDeployment->last_result_received);
    }

    /**
     * @param list<string> $statuses
     * @param list<string> $nameservers
     */
    private function makeRemoteDomainDto(
        string $domainName,
        array $statuses,
        array $nameservers,
        ?DateTime $expiryDate = null,
    ): DomainDetailsDTO {
        return new DomainDetailsDTO(
            domainName: $domainName,
            registrant: 'test-registrant',
            status: $statuses,
            autoRenew: false,
            autoRenewPeriod: 1,
            ns: $nameservers,
            premium: false,
            expiryDate: $expiryDate,
        );
    }

    /**
     * @param list<Process> $processes
     */
    private function createProcessCollection(array $processes): ProcessCollection
    {
        return ProcessCollection::fromArray([
            'entities' => array_map(
                static fn (Process $process): array => $process->toArray(),
                $processes,
            ),
        ]);
    }

    private function createProcess(
        int $processId,
        string $status,
        string $type,
        string $createdDate,
    ): Process {
        return Process::fromArray([
            'id' => $processId,
            'user' => 'test-user',
            'customer' => 'test-customer',
            'status' => $status,
            'statusDetail' => null,
            'createdDate' => $createdDate,
            'type' => $type,
            'identifier' => 'example.test',
            'action' => 'register',
            'command' => [],
        ]);
    }

    /**
     * @param array<string, mixed> $expectedSubset
     */
    private function assertDeploymentLastResultContains(
        DomainDeployment $domainDeployment,
        array $expectedSubset,
    ): void {
        $domainDeployment->refresh();

        self::assertNotNull($domainDeployment->last_result);
        self::assertNotNull($domainDeployment->last_result_received);

        $decoded = json_decode($domainDeployment->last_result, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        foreach ($expectedSubset as $key => $value) {
            self::assertArrayHasKey($key, $decoded);
            self::assertSame($value, $decoded[$key]);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function assertBaseLogContext(array $context, DomainDeployment $domainDeployment): bool
    {
        return ($context[LoggingContextKeys::ONE_OFF_SCRIPT] ?? null) === self::SOURCE
            && ($context[LoggingContextKeys::SUBSCRIPTION_UUID] ?? null) === $domainDeployment->subscription->uuid
            && ($context[LoggingContextKeys::DOMAIN_NAME] ?? null) === $domainDeployment->subscription->domain
            && ($context[LoggingContextKeys::PROVISIONING_ID] ?? null) === $domainDeployment->id;
    }
}
