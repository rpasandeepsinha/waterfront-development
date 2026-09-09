<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Jobs;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Jobs\ReceiveWpInstallationIdJob;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;
use Waterfront\Domain\Hosting\WpToolkit\WpToolkitService;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;

#[CoversClass(ReceiveWpInstallationIdJob::class)]
class ReceiveWpInstallationIdJobTest extends IntegrationTestCase
{
    private const int MAX_ATTEMPTS = 7;

    #[Test]
    public function jobDispatch(): void
    {
        Queue::fake();
        Queue::assertNothingPushed();

        self::resolve(Dispatcher::class)
            ->dispatch(
                new ReceiveWpInstallationIdJob(
                    'xxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
                    self::createStub(Server::class),
                )
            );

        Queue::assertPushedOn(QueueName::HOSTING->value, ReceiveWpInstallationIdJob::class);
    }

    #[Test]
    public function jobDispatchAsync(): void
    {
        Bus::fake();

        self::resolve(Dispatcher::class)
            ->dispatch(
                new ReceiveWpInstallationIdJob(
                    'xxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
                    self::createStub(Server::class),
                )
            );

        Bus::assertNotDispatchedSync(ReceiveWpInstallationIdJob::class);
    }

    #[Test]
    public function handleSuccess(): void
    {
        $testDomain = 'test-domain.nl';
        $wpInstallationId = 1908;

        $subscriptionRepository = self::resolve(SubscriptionRepository::class);
        $hostingDeploymentRepository = self::resolve(HostingDeploymentRepository::class);

        $serverMock = self::createStub(Server::class);
        $wpToolkitServiceMock = self::createMock(WpToolkitService::class);
        $loggerMock = self::createMock(LoggerInterface::class);

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->forDomain($testDomain)
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->for(
                new ProductFactory()
                    ->for(new ProductGroupFactory()->hosting())
            )
            ->createOne();

        new HostingDeploymentFactory()
            ->for($subscription)
            ->createOne();

        $wpToolkitServiceMock->expects(self::once())
            ->method('instantiateClient')
            ->with($serverMock)
            ->willReturn($wpToolkitServiceMock);

        $wpToolkitServiceMock->expects(self::once())
            ->method('getWpInstallationId')
            ->with($testDomain)
            ->willReturn($wpInstallationId);

        $job = new ReceiveWpInstallationIdJob(
            subscriptionUuid: $subscription->uuid,
            server: $serverMock
        );

        $loggerMock->expects(self::exactly(3))
            ->method('debug')
            ->with(
                ...self::withConsecutive(
                    [
                        sprintf(
                            'Starting ReceiveWpInstallationId Job for domain [{domain.name}]. attempt {queue.attempt}/{%d}',
                            self::MAX_ATTEMPTS
                        ),
                        [
                            LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                            LoggingContextKeys::QUEUE_ATTEMPT => 1,
                        ],
                    ],
                    [
                        'WpToolkitInstallationId found for domain [{domain.name}]',
                        [
                            LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                            LoggingContextKeys::META        => [
                                'WpToolkitInstallationId' => $wpInstallationId,
                            ],
                        ],
                    ],
                    [
                        'The received installationId for domain [{domain.name}]. Is stored',
                        [
                            LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                            LoggingContextKeys::META => [
                                'WpToolkitInstallationId' => $wpInstallationId,
                            ],
                        ],
                    ]
                )
            );

        $job->handle(
            subscriptionRepository: $subscriptionRepository,
            deploymentRepository: $hostingDeploymentRepository,
            wpToolkitService: $wpToolkitServiceMock,
            logger: $loggerMock
        );

        $subscription->refresh();
        self::assertSame(TechnicalStatus::OK->value, $subscription->technical_status);
        self::assertInstanceOf(HostingDeployment::class, $subscription->hostingDeployment);
        self::assertSame($wpInstallationId, $subscription->hostingDeployment->wp_installation_id);
    }

    #[Test]
    public function handleIdNotFound(): void
    {
        $testDomain = 'test-domain.nl';

        $subscriptionRepository = self::resolve(SubscriptionRepository::class);
        $hostingDeploymentRepository = self::resolve(HostingDeploymentRepository::class);

        $serverMock = self::createStub(Server::class);
        $wpToolkitServiceMock = self::createMock(WpToolkitService::class);
        $loggerMock = self::createMock(LoggerInterface::class);

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->forDomain($testDomain)
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->for(
                new ProductFactory()
                    ->for(new ProductGroupFactory()->hosting())
            )
            ->createOne();

        new HostingDeploymentFactory()
            ->for($subscription)
            ->createOne();

        $wpToolkitServiceMock->expects(self::once())
            ->method('instantiateClient')
            ->with($serverMock)
            ->willReturn($wpToolkitServiceMock);

        $wpToolkitServiceMock->expects(self::once())
            ->method('getWpInstallationId')
            ->with($testDomain)
            ->willReturn(null);

        $job = new ReceiveWpInstallationIdJob(
            subscriptionUuid: $subscription->uuid,
            server: $serverMock
        );

        $loggerMock->expects(self::exactly(2))
            ->method('debug')
            ->with(
                ...self::withConsecutive(
                    [
                        sprintf(
                            'Starting ReceiveWpInstallationId Job for domain [{domain.name}]. attempt {queue.attempt}/{%d}',
                            self::MAX_ATTEMPTS
                        ),
                        [
                            LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                            LoggingContextKeys::QUEUE_ATTEMPT => 1,
                        ],
                    ],
                    [
                        sprintf(
                            'No WpToolkitInstallationId found(yet) for domain [{domain.name}]. attempt {queue.attempt}/%d',
                            self::MAX_ATTEMPTS
                        ),
                        [
                            LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                            LoggingContextKeys::QUEUE_ATTEMPT => 1,
                        ],
                    ]
                )
            );

        $job->handle(
            subscriptionRepository: $subscriptionRepository,
            deploymentRepository: $hostingDeploymentRepository,
            wpToolkitService: $wpToolkitServiceMock,
            logger: $loggerMock
        );

        $subscription->refresh();
        self::assertSame(TechnicalStatus::PENDING->value, $subscription->technical_status);
        self::assertInstanceOf(HostingDeployment::class, $subscription->hostingDeployment);
        self::assertNull($subscription->hostingDeployment->wp_installation_id);
    }

    #[Test]
    public function handleFailedDueException(): void
    {
        $testDomain = 'test-domain.nl';

        $serverMock = self::createStub(Server::class);
        $loggerMock = self::createMock(LoggerInterface::class);
        $guzzleExceptionMock = self::createStub(GuzzleException::class);

        $this->app->bind(LoggerInterface::class, fn () => $loggerMock);

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->forDomain($testDomain)
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->for(
                new ProductFactory()
                    ->for(new ProductGroupFactory()->hosting())
            )
            ->createOne();

        new HostingDeploymentFactory()
            ->for($subscription)
            ->createOne();

        $loggerMock->expects(self::once())
            ->method('error')
        ->with(
            'Error ReceiveWpInstallationIdJob while request the WpToolkitInstallationId job definitely failed after {queue.attempt} attempts',
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::QUEUE_ATTEMPT => 1,
                LoggingContextKeys::EXCEPTION => $guzzleExceptionMock,
            ]
        );

        $job = new ReceiveWpInstallationIdJob(
            subscriptionUuid: $subscription->uuid,
            server: $serverMock
        );

        $job->failed($guzzleExceptionMock);

        $subscription->refresh();
        self::assertSame(TechnicalStatus::FAILED->value, $subscription->technical_status);
        self::assertInstanceOf(HostingDeployment::class, $subscription->hostingDeployment);
        self::assertNull($subscription->hostingDeployment->wp_installation_id);
    }
}
