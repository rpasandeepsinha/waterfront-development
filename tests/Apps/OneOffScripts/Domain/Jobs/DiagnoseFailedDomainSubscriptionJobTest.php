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
use RealtimeRegister\Domain\ProcessCollection;
use Tests\Factories\SubscriptionFactory;
use Tests\TestCase;
use Waterfront\Apps\OneOffScripts\Domain\DTO\FailedDomainSubscriptionRepair;
use Waterfront\Apps\OneOffScripts\Domain\Enum\FailedDomainSubscriptionRepairPath;
use Waterfront\Apps\OneOffScripts\Domain\Factories\FailedDomainSubscriptionRepairJobFactory;
use Waterfront\Apps\OneOffScripts\Domain\Jobs\DiagnoseFailedDomainSubscriptionJob;
use Waterfront\Apps\OneOffScripts\Domain\Jobs\RetryDomainProvisioningJob;
use Waterfront\Apps\OneOffScripts\Domain\Services\FailedDomainSubscriptionRepairService;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;

#[CoversClass(DiagnoseFailedDomainSubscriptionJob::class)]
#[AllowMockObjectsWithoutExpectations]
class DiagnoseFailedDomainSubscriptionJobTest extends TestCase
{
    private const string TRIGGERED_BY = 'fix-failed-domain-subscriptions';

    private MockObject&Dispatcher $dispatcher;

    private MockObject&FailedDomainSubscriptionRepairService $failedDomainSubscriptionRepairService;

    private Subscription $subscription;

    private DomainDeployment $domainDeployment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = self::createMock(Dispatcher::class);
        $this->failedDomainSubscriptionRepairService = self::createMock(FailedDomainSubscriptionRepairService::class);

        $this->subscription = SubscriptionFactory::new()
            ->forDomain('example.test')
            ->makeOne();

        $this->domainDeployment = new DomainDeployment();
        $this->domainDeployment->id = 101;

        $this->domainDeployment->setRelation('subscription', $this->subscription);
        $this->subscription->setRelation('domainDeployment', $this->domainDeployment);
    }

    #[Test]
    public function queueDispatchesOnDefaultQueue(): void
    {
        Queue::fake();

        $this->app->make(Dispatcher::class)->dispatch(new DiagnoseFailedDomainSubscriptionJob(
            subscription: $this->subscription,
            dryRun: true,
            triggeredBy: self::TRIGGERED_BY,
        ));

        Queue::assertPushedOn(QueueName::DEFAULT->value, DiagnoseFailedDomainSubscriptionJob::class);
    }

    #[Test]
    public function jobShouldBeAsyncOnQueue(): void
    {
        Bus::fake();

        $this->app->make(Dispatcher::class)->dispatch(new DiagnoseFailedDomainSubscriptionJob(
            subscription: $this->subscription,
            dryRun: true,
            triggeredBy: self::TRIGGERED_BY,
        ));

        Bus::assertNotDispatchedSync(DiagnoseFailedDomainSubscriptionJob::class);
    }

    #[Test]
    public function handleDispatchesJobReturnedByFactory(): void
    {
        $repairPlan = new FailedDomainSubscriptionRepair(
            path: FailedDomainSubscriptionRepairPath::RETRY_PROVISIONING,
            reason: 'remote_domain_missing',
        );

        $expectedJob = new RetryDomainProvisioningJob(
            subscription: $this->subscription,
            dryRun: false,
            triggeredBy: self::TRIGGERED_BY,
        );

        $repairJobFactory = self::createMock(FailedDomainSubscriptionRepairJobFactory::class);

        $this->failedDomainSubscriptionRepairService
            ->expects(self::once())
            ->method('determineRepair')
            ->with($this->subscription, self::TRIGGERED_BY)
            ->willReturn($repairPlan);

        $repairJobFactory
            ->expects(self::once())
            ->method('make')
            ->with($repairPlan, $this->subscription, false, self::TRIGGERED_BY)
            ->willReturn($expectedJob);

        $this->dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with($expectedJob);

        $job = new DiagnoseFailedDomainSubscriptionJob(
            subscription: $this->subscription,
            dryRun: false,
            triggeredBy: self::TRIGGERED_BY,
        );

        $job->handle(
            dispatcher: $this->dispatcher,
            repairJobFactory: $repairJobFactory,
            failedDomainSubscriptionRepairService: $this->failedDomainSubscriptionRepairService,
            logger: self::createStub(LoggerInterface::class),
        );
    }

    #[Test]
    public function handleDoesNotDispatchWhenFactoryReturnsNull(): void
    {
        $repairPlan = new FailedDomainSubscriptionRepair(
            path: FailedDomainSubscriptionRepairPath::SKIP,
            reason: 'missing_dns_deployment',
        );

        $repairJobFactory = self::createMock(FailedDomainSubscriptionRepairJobFactory::class);

        $this->failedDomainSubscriptionRepairService
            ->expects(self::once())
            ->method('determineRepair')
            ->with($this->subscription, self::TRIGGERED_BY)
            ->willReturn($repairPlan);

        $repairJobFactory
            ->expects(self::once())
            ->method('make')
            ->with($repairPlan, $this->subscription, true, self::TRIGGERED_BY)
            ->willReturn(null);

        $this->dispatcher
            ->expects(self::never())
            ->method('dispatch');

        $job = new DiagnoseFailedDomainSubscriptionJob(
            subscription: $this->subscription,
            dryRun: true,
            triggeredBy: self::TRIGGERED_BY,
        );

        $job->handle(
            dispatcher: $this->dispatcher,
            repairJobFactory: $repairJobFactory,
            failedDomainSubscriptionRepairService: $this->failedDomainSubscriptionRepairService,
            logger: self::createStub(LoggerInterface::class),
        );
    }

    #[Test]
    public function handleLogsDiagnosisWithRepairPlanMetadata(): void
    {
        $processCollection = ProcessCollection::fromArray([]);

        $repairPlan = new FailedDomainSubscriptionRepair(
            path: FailedDomainSubscriptionRepairPath::RESTORE_FROM_PROCESS,
            processCollection: $processCollection,
            reason: 'remote_processes_found_no_domain',
        );

        $expectedJob = new RetryDomainProvisioningJob(
            subscription: $this->subscription,
            dryRun: false,
            triggeredBy: self::TRIGGERED_BY,
        );

        $repairJobFactory = self::createMock(FailedDomainSubscriptionRepairJobFactory::class);

        $this->failedDomainSubscriptionRepairService
            ->expects(self::once())
            ->method('determineRepair')
            ->with($this->subscription, self::TRIGGERED_BY)
            ->willReturn($repairPlan);

        $repairJobFactory
            ->expects(self::once())
            ->method('make')
            ->with($repairPlan, $this->subscription, false, self::TRIGGERED_BY)
            ->willReturn($expectedJob);

        $this->dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with($expectedJob);

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Diagnosed failed domain subscription repair path.',
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => self::TRIGGERED_BY,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
                    LoggingContextKeys::PROVISIONING_ID => $this->domainDeployment->id,
                    LoggingContextKeys::META => [
                        'repair_path' => FailedDomainSubscriptionRepairPath::RESTORE_FROM_PROCESS->value,
                        'reason' => 'remote_processes_found_no_domain',
                        'processes' => $processCollection->toArray(),
                        'dry_run' => false,
                    ],
                ],
            );

        $job = new DiagnoseFailedDomainSubscriptionJob(
            subscription: $this->subscription,
            dryRun: false,
            triggeredBy: self::TRIGGERED_BY,
        );

        $job->handle(
            dispatcher: $this->dispatcher,
            repairJobFactory: $repairJobFactory,
            failedDomainSubscriptionRepairService: $this->failedDomainSubscriptionRepairService,
            logger: $logger,
        );
    }
}
