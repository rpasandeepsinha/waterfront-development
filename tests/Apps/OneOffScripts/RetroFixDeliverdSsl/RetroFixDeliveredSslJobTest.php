<?php

declare(strict_types=1);

namespace Tests\Apps\OneOffScripts\RetroFixDeliverdSsl;

use Carbon\CarbonImmutable;
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
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\OneOffScripts\RetroFixDeliverdSsl\RetroFixDeliveredSslJob;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\RtrClient\Services\RtrSslService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;

#[CoversClass(RetroFixDeliveredSslJob::class)]
#[AllowMockObjectsWithoutExpectations]
class RetroFixDeliveredSslJobTest extends IntegrationTestCase
{
    private MockObject&LoggerInterface $logger;

    private MockObject&RtrSslService $rtrSslService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = self::createMock(LoggerInterface::class);
        $this->rtrSslService = self::createMock(RtrSslService::class);
    }

    #[Test]
    public function jobIsDispatchedOnOneTimeScriptsQueue(): void
    {
        Queue::fake();

        $customer = new CustomerFactory()->createOne();
        $product = ProductFactory::new()->sslSingleDomain()->createOne();
        $subscription = SubscriptionFactory::new()
            ->for($customer)
            ->for($product)
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->forDomain('example.com')
            ->createOne();

        self::resolve(Dispatcher::class)
            ->dispatch(new RetroFixDeliveredSslJob(
                subscription: $subscription,
                isDryRun: false,
            ));

        Queue::assertPushedOn(QueueName::ONE_TIME_SCRIPTS->value, RetroFixDeliveredSslJob::class);
    }

    #[Test]
    public function jobIsAsync(): void
    {
        Bus::fake();

        $customer = new CustomerFactory()->createOne();
        $product = ProductFactory::new()->sslSingleDomain()->createOne();
        $subscription = SubscriptionFactory::new()
            ->for($customer)
            ->for($product)
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->forDomain('example.com')
            ->createOne();

        self::resolve(Dispatcher::class)
            ->dispatch(new RetroFixDeliveredSslJob(
                subscription: $subscription,
                isDryRun: false,
            ));

        Bus::assertNotDispatchedSync(RetroFixDeliveredSslJob::class);
    }

    #[Test]
    public function handleSkipsSubscriptionWithNullDomain(): void
    {
        $customer = new CustomerFactory()->createOne();
        $product = ProductFactory::new()->sslSingleDomain()->createOne();

        $subscription = SubscriptionFactory::new()
            ->for($customer)
            ->for($product)
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->state(['domain' => null])
            ->createOne();

        $this->rtrSslService->expects(self::never())->method('getLatestSslProcess');

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                sprintf(
                    'Skipping subscription %d due to missing domain or SSL deployment or installation error.',
                    $subscription->id,
                ),
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => 'retro-fix-deliverd-ssl',
                    LoggingContextKeys::META => [
                        LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                        LoggingContextKeys::META => [
                            'dry-run' => false,
                            'ssl_deployment' => null,
                            'ssl_status' => null,
                            'ssl_domain' => null,
                        ],
                    ],
                ],
            );

        $job = new RetroFixDeliveredSslJob(
            subscription: $subscription,
            isDryRun: false,
        );

        $job->handle($this->logger, $this->rtrSslService);
    }

    #[Test]
    public function handleSkipsSubscriptionWithNullSslDeployment(): void
    {
        $customer = new CustomerFactory()->createOne();
        $product = ProductFactory::new()->sslSingleDomain()->createOne();

        $subscription = SubscriptionFactory::new()
            ->for($customer)
            ->for($product)
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->forDomain('example-no-ssl.com')
            ->createOne();

        $this->rtrSslService->expects(self::never())->method('getLatestSslProcess');

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                sprintf(
                    'Skipping subscription %d due to missing domain or SSL deployment or installation error.',
                    $subscription->id,
                ),
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => 'retro-fix-deliverd-ssl',
                    LoggingContextKeys::META => [
                        LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                        LoggingContextKeys::META => [
                            'dry-run' => false,
                            'ssl_deployment' => null,
                            'ssl_status' => null,
                            'ssl_domain' => 'example-no-ssl.com',
                        ],
                    ],
                ],
            );

        $job = new RetroFixDeliveredSslJob(
            subscription: $subscription,
            isDryRun: false,
        );

        $job->handle($this->logger, $this->rtrSslService);
    }

    #[Test]
    public function handleSkipsSubscriptionWithInstallationErrorStatus(): void
    {
        $customer = new CustomerFactory()->createOne();
        $product = ProductFactory::new()->sslSingleDomain()->createOne();

        $subscription = SubscriptionFactory::new()
            ->for($customer)
            ->for($product)
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->forDomain('example-install-error.com')
            ->createOne();

        $sslDeployment = SslDeploymentFactory::new()->rtrProvider()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'last_result' => json_encode([
                'certificate_status' => 'Installation error.',
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->rtrSslService->expects(self::never())->method('getLatestSslProcess');

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                sprintf(
                    'Skipping subscription %d due to missing domain or SSL deployment or installation error.',
                    $subscription->id,
                ),
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => 'retro-fix-deliverd-ssl',
                    LoggingContextKeys::META => [
                        LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                        LoggingContextKeys::META => [
                            'dry-run' => false,
                            'ssl_deployment' => $sslDeployment->id,
                            'ssl_status' => 'Installation error.',
                            'ssl_domain' => 'example-install-error.com',
                        ],
                    ],
                ],
            );

        $job = new RetroFixDeliveredSslJob(
            subscription: $subscription->refresh(),
            isDryRun: false,
        );

        $job->handle($this->logger, $this->rtrSslService);
    }

    #[Test]
    public function handleUpdatesSubscriptionAndSslDeploymentWhenRtrHasCompletedSsl(): void
    {
        $customer = new CustomerFactory()->createOne();
        $product = ProductFactory::new()->sslSingleDomain()->createOne();

        $subscription = SubscriptionFactory::new()
            ->for($customer)
            ->for($product)
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->forDomain('example-completed.com')
            ->createOne();

        $sslDeployment = SslDeploymentFactory::new()->rtrProvider()->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);

        $this->rtrSslService
            ->expects(self::once())
            ->method('getLatestSslProcess')
            ->with('example-completed.com')
            ->willReturn(ProcessCollection::fromArray([
                'entities' => [
                    [
                        'id' => 1,
                        'user' => 'test-user',
                        'customer' => 'test-customer',
                        'type' => 'certificate',
                        'identifier' => 'example-completed.com',
                        'action' => 'create',
                        'command' => ['create'],
                        'status' => 'COMPLETED',
                        'createdDate' => '2025-01-01 00:00:00',
                        'updatedDate' => '2025-01-01 00:00:00',
                    ],
                ],
            ]));

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                sprintf(
                    'Checked SSL deployment for with domain %s, ssl completed at RTR: %s',
                    'example-completed.com',
                    'yes',
                ),
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => 'retro-fix-deliverd-ssl',
                    LoggingContextKeys::META => [
                        LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                        LoggingContextKeys::META => [
                            'dry-run' => false,
                            'ssl_deployment' => $sslDeployment->id,
                            'ssl_status' => $sslDeployment->status,
                            'ssl_domain' => 'example-completed.com',
                            'latest_process_status' => ProcessStatusEnum::STATUS_COMPLETED,
                        ],
                    ],
                ],
            );

        $job = new RetroFixDeliveredSslJob(
            subscription: $subscription->refresh(),
            isDryRun: false,
        );

        $job->handle($this->logger, $this->rtrSslService);

        $subscription->refresh();
        self::assertSame(TechnicalStatus::OK->value, $subscription->technical_status);

        $sslDeployment->refresh();
        self::assertSame(
            'Set to OK because RTR has a completed SSL process for this domain.',
            $sslDeployment->last_result,
        );
        self::assertNotNull($sslDeployment->last_result_received);
        self::assertSame(
            CarbonImmutable::now()->format('Y-m-d H:i:s'),
            $sslDeployment->last_result_received->format('Y-m-d H:i:s'),
        );
    }

    #[Test]
    public function handleDoesNotUpdateSubscriptionWhenLatestProcessIsNotCompleted(): void
    {
        $customer = new CustomerFactory()->createOne();
        $product = ProductFactory::new()->sslSingleDomain()->createOne();

        $subscription = SubscriptionFactory::new()
            ->for($customer)
            ->for($product)
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->forDomain('example-not-completed.com')
            ->createOne();

        $sslDeployment = SslDeploymentFactory::new()->rtrProvider()->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);

        $originalTechnicalStatus = $subscription->technical_status;
        $originalLastResult = $sslDeployment->last_result;

        $this->rtrSslService
            ->expects(self::once())
            ->method('getLatestSslProcess')
            ->with('example-not-completed.com')
            ->willReturn(ProcessCollection::fromArray([
                'entities' => [
                    [
                        'id' => 2,
                        'user' => 'test-user',
                        'customer' => 'test-customer',
                        'type' => 'certificate',
                        'identifier' => 'example-not-completed.com',
                        'action' => 'create',
                        'command' => ['create'],
                        'status' => 'FAILED',
                        'createdDate' => '2025-06-01 00:00:00',
                        'updatedDate' => '2025-06-01 00:00:00',
                    ],
                ],
            ]));

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                sprintf(
                    'Checked SSL deployment for with domain %s, ssl completed at RTR: %s',
                    'example-not-completed.com',
                    'no',
                ),
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => 'retro-fix-deliverd-ssl',
                    LoggingContextKeys::META => [
                        LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                        LoggingContextKeys::META => [
                            'dry-run' => false,
                            'ssl_deployment' => $sslDeployment->id,
                            'ssl_status' => $sslDeployment->status,
                            'ssl_domain' => 'example-not-completed.com',
                            'latest_process_status' => ProcessStatusEnum::STATUS_FAILED,
                        ],
                    ],
                ],
            );

        $job = new RetroFixDeliveredSslJob(
            subscription: $subscription->refresh(),
            isDryRun: false,
        );

        $job->handle($this->logger, $this->rtrSslService);

        $subscription->refresh();
        self::assertSame($originalTechnicalStatus, $subscription->technical_status);

        $sslDeployment->refresh();
        self::assertSame($originalLastResult, $sslDeployment->last_result);
    }

    #[Test]
    public function handleDoesNotUpdateSubscriptionWhenNoProcessExists(): void
    {
        $customer = new CustomerFactory()->createOne();
        $product = ProductFactory::new()->sslSingleDomain()->createOne();

        $subscription = SubscriptionFactory::new()
            ->for($customer)
            ->for($product)
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->forDomain('example-no-process.com')
            ->createOne();

        SslDeploymentFactory::new()->rtrProvider()->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);

        $originalTechnicalStatus = $subscription->technical_status;

        $this->rtrSslService
            ->expects(self::once())
            ->method('getLatestSslProcess')
            ->with('example-no-process.com')
            ->willReturn(ProcessCollection::fromArray([
                'entities' => [],
            ]));

        $this->logger->expects(self::once())->method('info');

        $job = new RetroFixDeliveredSslJob(
            subscription: $subscription->refresh(),
            isDryRun: false,
        );

        $job->handle($this->logger, $this->rtrSslService);

        $subscription->refresh();
        self::assertSame($originalTechnicalStatus, $subscription->technical_status);
    }

    #[Test]
    public function handleDryRunDoesNotUpdateSubscription(): void
    {
        $customer = new CustomerFactory()->createOne();
        $product = ProductFactory::new()->sslSingleDomain()->createOne();

        $subscription = SubscriptionFactory::new()
            ->for($customer)
            ->for($product)
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->forDomain('example-dryrun.com')
            ->createOne();

        SslDeploymentFactory::new()->rtrProvider()->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);

        $originalTechnicalStatus = $subscription->technical_status;

        $this->rtrSslService
            ->expects(self::once())
            ->method('getLatestSslProcess')
            ->with('example-dryrun.com')
            ->willReturn(ProcessCollection::fromArray([
                'entities' => [
                    [
                        'id' => 1,
                        'user' => 'test-user',
                        'customer' => 'test-customer',
                        'type' => 'certificate',
                        'identifier' => 'example-dryrun.com',
                        'action' => 'create',
                        'command' => ['create'],
                        'status' => 'COMPLETED',
                        'createdDate' => '2025-01-01 00:00:00',
                        'updatedDate' => '2025-01-01 00:00:00',
                    ],
                ],
            ]));

        $this->logger->expects(self::once())->method('info');

        $job = new RetroFixDeliveredSslJob(
            subscription: $subscription->refresh(),
            isDryRun: true,
        );

        $job->handle($this->logger, $this->rtrSslService);

        $subscription->refresh();
        self::assertSame($originalTechnicalStatus, $subscription->technical_status);
    }

    #[Test]
    public function handleDoesNotCrashWhenLastResultContainsInvalidJson(): void
    {
        $customer = new CustomerFactory()->createOne();
        $product = ProductFactory::new()->sslSingleDomain()->createOne();

        $subscription = SubscriptionFactory::new()
            ->for($customer)
            ->for($product)
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->forDomain('example-invalid-json.com')
            ->createOne();

        $sslDeployment = SslDeploymentFactory::new()->rtrProvider()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'last_result' => 'Set to OK because RTR has a completed SSL process for this domain.',
        ]);

        $this->rtrSslService
            ->expects(self::once())
            ->method('getLatestSslProcess')
            ->with('example-invalid-json.com')
            ->willReturn(ProcessCollection::fromArray([
                'entities' => [
                    [
                        'id' => 1,
                        'user' => 'test-user',
                        'customer' => 'test-customer',
                        'type' => 'certificate',
                        'identifier' => 'example-invalid-json.com',
                        'action' => 'create',
                        'command' => ['create'],
                        'status' => 'COMPLETED',
                        'createdDate' => '2025-01-01 00:00:00',
                        'updatedDate' => '2025-01-01 00:00:00',
                    ],
                ],
            ]));

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                sprintf(
                    'Checked SSL deployment for with domain %s, ssl completed at RTR: %s',
                    'example-invalid-json.com',
                    'yes',
                ),
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => 'retro-fix-deliverd-ssl',
                    LoggingContextKeys::META => [
                        LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                        LoggingContextKeys::META => [
                            'dry-run' => false,
                            'ssl_deployment' => $sslDeployment->id,
                            'ssl_status' => 'UNKNOWN',
                            'ssl_domain' => 'example-invalid-json.com',
                            'latest_process_status' => ProcessStatusEnum::STATUS_COMPLETED,
                        ],
                    ],
                ],
            );

        $job = new RetroFixDeliveredSslJob(
            subscription: $subscription->refresh(),
            isDryRun: false,
        );

        $job->handle($this->logger, $this->rtrSslService);

        $subscription->refresh();
        self::assertSame(TechnicalStatus::OK->value, $subscription->technical_status);
    }
}
