<?php

declare(strict_types=1);

namespace Tests\Apps\OneOffScripts\Domain\Jobs;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\Enum\ProcessStatusEnum;
use RealtimeRegister\Domain\ProcessCollection;
use Tests\Factories\SubscriptionFactory;
use Tests\TestCase;
use Waterfront\Apps\OneOffScripts\Domain\Jobs\RestoreFromProcesses;
use Waterfront\Apps\OneOffScripts\Domain\Services\FailedDomainSubscriptionRepairService;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;

#[CoversClass(RestoreFromProcesses::class)]
#[AllowMockObjectsWithoutExpectations]
class RestoreFromProcessesJobTest extends TestCase
{
    private const string TRIGGERED_BY = 'fix-failed-domain-subscriptions';
    private const int RTR_PROCESS_ID = 9001;

    private MockObject&FailedDomainSubscriptionRepairService $failedDomainSubscriptionRepairService;

    private LoggerInterface $logger;

    private Subscription $subscription;

    private DomainDeployment $domainDeployment;

    private ProcessCollection $processCollection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->failedDomainSubscriptionRepairService = self::createMock(FailedDomainSubscriptionRepairService::class);
        $this->logger = self::createStub(LoggerInterface::class);

        $this->subscription = SubscriptionFactory::new()
            ->forDomain('example.test')
            ->makeOne();

        $this->domainDeployment = new DomainDeployment();
        $this->domainDeployment->id = 101;

        $this->subscription->setRelation('domainDeployment', $this->domainDeployment);
        $this->domainDeployment->setRelation('subscription', $this->subscription);

        $this->processCollection = ProcessCollection::fromArray([[
            'id' => self::RTR_PROCESS_ID,
            'user' => 'sandwave',
            'customer' => 'sandwave',
            'status' => ProcessStatusEnum::STATUS_RUNNING,
            'createdDate' => '2020-03-04 12:34:56',
            'updatedDate' => '2021-03-04 12:34:56',
            'startedDate' => '2021-03-04 12:34:56',
            'type' => 'domain',
            'identifier' => 'example.nl',
            'action' => 'update',
            'command' => [],
        ]]);
    }

    #[Test]
    public function queueDispatchesOnDefaultQueue(): void
    {
        Queue::fake();

        $this->app->make(Dispatcher::class)->dispatch(new RestoreFromProcesses(
            processCollection: $this->processCollection,
            subscription: $this->subscription,
            dryRun: true,
            triggeredBy: self::TRIGGERED_BY,
        ));

        Queue::assertPushedOn(QueueName::DEFAULT->value, RestoreFromProcesses::class);
    }

    #[Test]
    public function jobShouldBeAsyncOnQueue(): void
    {
        Bus::fake();

        $this->app->make(Dispatcher::class)->dispatch(new RestoreFromProcesses(
            processCollection: $this->processCollection,
            subscription: $this->subscription,
            dryRun: true,
            triggeredBy: self::TRIGGERED_BY,
        ));

        Bus::assertNotDispatchedSync(RestoreFromProcesses::class);
    }

    #[Test]
    public function handleDoesNotCallRepairServiceInDryRun(): void
    {
        $this->failedDomainSubscriptionRepairService
            ->expects(self::never())
            ->method('restoreFromProcesses');

        $job = new RestoreFromProcesses(
            processCollection: $this->processCollection,
            subscription: $this->subscription,
            dryRun: true,
            triggeredBy: self::TRIGGERED_BY,
        );

        $job->handle(
            failedDomainSubscriptionRepairService: $this->failedDomainSubscriptionRepairService,
            logger: $this->logger,
        );
    }

    #[Test]
    public function handleCallsRepairServiceWhenNotDryRun(): void
    {
        $this->failedDomainSubscriptionRepairService
            ->expects(self::once())
            ->method('restoreFromProcesses')
            ->with(
                subscription: $this->subscription,
                source: self::TRIGGERED_BY,
                processCollection: $this->processCollection,
            );

        $job = new RestoreFromProcesses(
            processCollection: $this->processCollection,
            subscription: $this->subscription,
            dryRun: false,
            triggeredBy: self::TRIGGERED_BY,
        );

        $job->handle(
            failedDomainSubscriptionRepairService: $this->failedDomainSubscriptionRepairService,
            logger: $this->logger,
        );
    }

    #[Test]
    public function handleLogsDryRunMessageWithMetadata(): void
    {
        $this->failedDomainSubscriptionRepairService
            ->expects(self::never())
            ->method('restoreFromProcesses');

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Dry run: would restore subscription because RTR still has processes.',
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => self::TRIGGERED_BY,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
                    LoggingContextKeys::PROVISIONING_ID => $this->domainDeployment->id,
                    LoggingContextKeys::META => [
                        'processes' => $this->processCollection->toArray(),
                        'dry_run' => true,
                    ],
                ],
            );

        $job = new RestoreFromProcesses(
            processCollection: $this->processCollection,
            subscription: $this->subscription,
            dryRun: true,
            triggeredBy: self::TRIGGERED_BY,
        );

        $job->handle(
            failedDomainSubscriptionRepairService: $this->failedDomainSubscriptionRepairService,
            logger: $logger,
        );
    }

    #[Test]
    public function handleLogsExecutingMessageWithMetadata(): void
    {
        $this->failedDomainSubscriptionRepairService
            ->expects(self::once())
            ->method('restoreFromProcesses')
            ->with(
                subscription: $this->subscription,
                source: self::TRIGGERED_BY,
                processes: $this->processCollection
            );

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Restoring failed subscription from open processes.',
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => self::TRIGGERED_BY,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
                    LoggingContextKeys::PROVISIONING_ID => $this->domainDeployment->id,
                    LoggingContextKeys::META => [
                        'processes' => $this->processCollection->toArray(),
                        'dry_run' => false,
                    ],
                ],
            );

        $job = new RestoreFromProcesses(
            processCollection: $this->processCollection,
            subscription: $this->subscription,
            dryRun: false,
            triggeredBy: self::TRIGGERED_BY,
        );

        $job->handle(
            failedDomainSubscriptionRepairService: $this->failedDomainSubscriptionRepairService,
            logger: $logger,
        );
    }
}
