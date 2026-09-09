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
use Tests\Factories\SubscriptionFactory;
use Tests\TestCase;
use Waterfront\Apps\OneOffScripts\Domain\Jobs\RepairMissingRemoteNameserversJob;
use Waterfront\Apps\OneOffScripts\Domain\Services\FailedDomainSubscriptionRepairService;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;

#[CoversClass(RepairMissingRemoteNameserversJob::class)]
#[AllowMockObjectsWithoutExpectations]
class RepairMissingRemoteNameserversJobTest extends TestCase
{
    private const string TRIGGERED_BY = 'fix-failed-domain-subscriptions';

    private MockObject&FailedDomainSubscriptionRepairService $failedDomainSubscriptionRepairService;

    private LoggerInterface $logger;

    private Subscription $subscription;

    private DomainDeployment $domainDeployment;

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
    }

    #[Test]
    public function queueDispatchesOnDefaultQueue(): void
    {
        Queue::fake();

        $this->app->make(Dispatcher::class)->dispatch(new RepairMissingRemoteNameserversJob(
            subscription: $this->subscription,
            dryRun: true,
            triggeredBy: self::TRIGGERED_BY,
        ));

        Queue::assertPushedOn(QueueName::DEFAULT->value, RepairMissingRemoteNameserversJob::class);
    }

    #[Test]
    public function jobShouldBeAsyncOnQueue(): void
    {
        Bus::fake();

        $this->app->make(Dispatcher::class)->dispatch(new RepairMissingRemoteNameserversJob(
            subscription: $this->subscription,
            dryRun: true,
            triggeredBy: self::TRIGGERED_BY,
        ));

        Bus::assertNotDispatchedSync(RepairMissingRemoteNameserversJob::class);
    }

    #[Test]
    public function handleDoesNotCallRepairServiceInDryRun(): void
    {
        $this->failedDomainSubscriptionRepairService
            ->expects(self::never())
            ->method('repairMissingRemoteNameservers');

        $job = new RepairMissingRemoteNameserversJob(
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
            ->method('repairMissingRemoteNameservers')
            ->with(
                $this->subscription,
                self::TRIGGERED_BY,
            );

        $job = new RepairMissingRemoteNameserversJob(
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
    public function handleLogsDryRunMessage(): void
    {
        $this->failedDomainSubscriptionRepairService
            ->expects(self::never())
            ->method('repairMissingRemoteNameservers');

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Dry run: would repair missing RTR nameservers for failed domain subscription.',
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => self::TRIGGERED_BY,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
                    LoggingContextKeys::PROVISIONING_ID => $this->domainDeployment->id,
                    LoggingContextKeys::META => [
                        'dry_run' => true,
                    ],
                ],
            );

        $job = new RepairMissingRemoteNameserversJob(
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
    public function handleLogsExecutingMessage(): void
    {
        $this->failedDomainSubscriptionRepairService
            ->expects(self::once())
            ->method('repairMissingRemoteNameservers')
            ->with(
                $this->subscription,
                self::TRIGGERED_BY,
            );

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Repairing missing RTR nameservers for failed domain subscription.',
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => self::TRIGGERED_BY,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
                    LoggingContextKeys::PROVISIONING_ID => $this->domainDeployment->id,
                    LoggingContextKeys::META => [
                        'dry_run' => false,
                    ],
                ],
            );

        $job = new RepairMissingRemoteNameserversJob(
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
