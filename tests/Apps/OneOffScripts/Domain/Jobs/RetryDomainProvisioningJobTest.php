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
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\TestCase;
use Waterfront\Apps\OneOffScripts\Domain\Jobs\RetryDomainProvisioningJob;
use Waterfront\Domain\Domains\Jobs\RegisterDomainNameJob;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Orders\DTO\CartOrderLines\MetaData\ExtensionMetaData;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\SubscriptionProcessor\Builder\EventSubscriptionDataBuilder;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;

#[CoversClass(RetryDomainProvisioningJob::class)]
#[AllowMockObjectsWithoutExpectations]
class RetryDomainProvisioningJobTest extends TestCase
{
    private const string TRIGGERED_BY = 'fix-failed-domain-subscriptions';

    private MockObject&Dispatcher $dispatcher;

    private LoggerInterface $logger;

    private MockObject&EventSubscriptionDataBuilder $eventSubscriptionDataBuilder;

    private Subscription $subscription;

    private DomainDeployment $domainDeployment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = self::createMock(Dispatcher::class);
        $this->logger = self::createStub(LoggerInterface::class);
        $this->eventSubscriptionDataBuilder = self::createMock(EventSubscriptionDataBuilder::class);

        $this->subscription = SubscriptionFactory::new()->forDomain('example.test')->makeOne();

        $this->domainDeployment = DomainDeploymentFactory::new()->makeOne([
            'subscription_uuid' => $this->subscription->uuid,
        ]);

        $this->subscription->setRelation('domainDeployment', $this->domainDeployment);
        $this->domainDeployment->setRelation('subscription', $this->subscription);
    }

    #[Test]
    public function queueDispatchesOnDefaultQueue(): void
    {
        Queue::fake();

        $this->app
            ->make(Dispatcher::class)
            ->dispatch(new RetryDomainProvisioningJob(
                subscription: $this->subscription,
                dryRun: true,
                triggeredBy: self::TRIGGERED_BY,
            ));

        Queue::assertPushedOn(QueueName::DEFAULT->value, RetryDomainProvisioningJob::class);
    }

    #[Test]
    public function jobShouldBeAsyncOnQueue(): void
    {
        Bus::fake();

        $this->app
            ->make(Dispatcher::class)
            ->dispatch(new RetryDomainProvisioningJob(
                subscription: $this->subscription,
                dryRun: true,
                triggeredBy: self::TRIGGERED_BY,
            ));

        Bus::assertNotDispatchedSync(RetryDomainProvisioningJob::class);
    }

    #[Test]
    public function handleDoesNotDispatchRegisterDomainNameJobInDryRun(): void
    {
        $this->dispatcher->expects(self::never())->method('dispatch');

        $job = new RetryDomainProvisioningJob(
            subscription: $this->subscription,
            dryRun: true,
            triggeredBy: self::TRIGGERED_BY,
        );

        $job->handle(
            dispatcher: $this->dispatcher,
            logger: $this->logger,
            eventSubscriptionDataBuilder: $this->eventSubscriptionDataBuilder,
        );
    }

    #[Test]
    public function handleDispatchesRegisterDomainNameJobWhenNotDryRun(): void
    {
        $queuedJobs = [];

        $this->dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $job) use (&$queuedJobs): void {
                $queuedJobs[] = $job;
            });

        $job = new RetryDomainProvisioningJob(
            subscription: $this->subscription,
            dryRun: false,
            triggeredBy: self::TRIGGERED_BY,
        );

        $job->handle(
            dispatcher: $this->dispatcher,
            logger: $this->logger,
            eventSubscriptionDataBuilder: $this->eventSubscriptionDataBuilder,
        );

        self::assertCount(1, $queuedJobs);
        self::assertEquals(
            new RegisterDomainNameJob($this->domainDeployment),
            $queuedJobs[0],
        );
    }

    #[Test]
    public function handleLogsDryRunMessage(): void
    {
        $this->dispatcher->expects(self::never())->method('dispatch');

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Dry run: would retry provisioning because domain does not exist at RTR.',
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

        $job = new RetryDomainProvisioningJob(
            subscription: $this->subscription,
            dryRun: true,
            triggeredBy: self::TRIGGERED_BY,
        );

        $job->handle(
            dispatcher: $this->dispatcher,
            logger: $logger,
            eventSubscriptionDataBuilder: $this->eventSubscriptionDataBuilder,
        );
    }

    #[Test]
    public function handleLogsExecutingMessage(): void
    {
        $this->dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (object $job): bool => $job instanceof RegisterDomainNameJob));

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Retrying provisioning because domain does not exist at RTR.',
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

        $job = new RetryDomainProvisioningJob(
            subscription: $this->subscription,
            dryRun: false,
            triggeredBy: self::TRIGGERED_BY,
        );

        $job->handle(
            dispatcher: $this->dispatcher,
            logger: $logger,
            eventSubscriptionDataBuilder: $this->eventSubscriptionDataBuilder,
        );
    }

    #[Test]
    public function handleLogsWhenMissingDomainDeploymentIsRecreated(): void
    {
        $this->subscription->setRelation('domainDeployment', null);
        $this->subscription->setRelation('orderLineItem', null);

        $recreatedDomainDeployment = DomainDeploymentFactory::new()->makeOne([
            'subscription_uuid' => $this->subscription->uuid,
        ]);
        $recreatedDomainDeployment->setRelation('subscription', $this->subscription);

        $extensionMetaData = self::createStub(ExtensionMetaData::class);

        $this->eventSubscriptionDataBuilder->method('buildExtensionMetaData')->willReturn($extensionMetaData);

        $this->eventSubscriptionDataBuilder->method('buildDnssecEnabled')->willReturn(true);

        $this->eventSubscriptionDataBuilder->method('buildPrivateWhoisStatus')->willReturn(false);

        $this->eventSubscriptionDataBuilder->method('buildDomainDeployment')->willReturn($recreatedDomainDeployment);

        $this->dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::equalTo(new RegisterDomainNameJob($recreatedDomainDeployment)));

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(self::exactly(2))
            ->method('info')
            ->with(
                ...self::withConsecutive(
                    [
                        'Retrying provisioning because domain does not exist at RTR.',
                        [
                            LoggingContextKeys::ONE_OFF_SCRIPT => self::TRIGGERED_BY,
                            LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                            LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
                            LoggingContextKeys::META => [
                                'dry_run' => false,
                            ],
                        ],
                    ],
                    [
                        'Created missing domain deployment before provisioning retry.',
                        [
                            LoggingContextKeys::ONE_OFF_SCRIPT => self::TRIGGERED_BY,
                            LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                            LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
                            LoggingContextKeys::PROVISIONING_ID => $recreatedDomainDeployment->id,
                            LoggingContextKeys::META => [
                                'dry_run' => false,
                                'reason' => 'missing_domain_deployment_recreated',
                            ],
                        ],
                    ],
                ),
            );

        $job = new RetryDomainProvisioningJob(
            subscription: $this->subscription,
            dryRun: false,
            triggeredBy: self::TRIGGERED_BY,
        );

        $job->handle(
            dispatcher: $this->dispatcher,
            logger: $logger,
            eventSubscriptionDataBuilder: $this->eventSubscriptionDataBuilder,
        );
    }
}
