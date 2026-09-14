<?php

declare(strict_types=1);

namespace Tests\Domain\VPS\Listeners;

use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\VPS\Events\CreateVps;
use Waterfront\Domain\VPS\Listeners\VpsCreationListener;
use Waterfront\Domain\VPS\Services\VpsService;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(VpsCreationListener::class)]
class VpsCreationListenerTest extends IntegrationTestCase
{
    #[Test]
    public function handleJob(): void
    {
        $vpsSubscription = new SubscriptionFactory()
            ->for(CustomerFactory::new()->createOne())
            ->for(new ProductFactory()->vps())
            ->createOne();

        $subscriptionRepository = self::createMock(SubscriptionRepository::class);
        $subscriptionRepository
            ->expects(self::once())
            ->method('getByUuid')
            ->with($vpsSubscription->uuid)
            ->willReturn($vpsSubscription);

        $mockVpsService = self::createMock(VpsService::class);
        $mockVpsService->expects(self::once())->method('create');

        $mockLogger = self::createMock(LoggerInterface::class);
        $mockLogger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Create VPS',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $vpsSubscription->uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProductGroupType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => 'cloudstack',
                ],
            );

        $createEvent = new CreateVps(
            subscriptionUuid: $vpsSubscription->uuid,
            sshKeyUuid: null,
        );

        $listener = new VpsCreationListener($mockVpsService, $mockLogger, $subscriptionRepository);
        $listener->handle($createEvent);
    }

    #[Test]
    public function handleFailedJob(): void
    {
        $customer = CustomerFactory::new()->createOne();

        $vpsSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->vps())
            ->has(new SubscriptionFactory()->for(new ProductFactory()->ubuntu())->for($customer), 'children')
            ->createOne(['technical_status' => TechnicalStatus::PENDING->value]);

        $subscriptionRepository = self::createMock(SubscriptionRepository::class);
        $subscriptionRepository
            ->expects(self::once())
            ->method('getByUuid')
            ->with($vpsSubscription->uuid)
            ->willReturn($vpsSubscription);

        $mockVpsService = self::createMock(VpsService::class);
        $mockVpsService->expects(self::never())->method('create');

        $mockLogger = self::createMock(LoggerInterface::class);
        $mockLogger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to create VPS',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $vpsSubscription->uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProductGroupType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => 'cloudstack',
                    LoggingContextKeys::EXCEPTION => new Exception('Test exception'),
                ],
            );

        $createEvent = new CreateVps(
            subscriptionUuid: $vpsSubscription->uuid,
            sshKeyUuid: null,
        );

        $listener = new VpsCreationListener($mockVpsService, $mockLogger, $subscriptionRepository);
        $listener->failed($createEvent, new Exception('Test exception'));

        $vpsSubscription->refresh();
        self::assertSame(TechnicalStatus::FAILED->value, $vpsSubscription->technical_status);
        self::assertSame(TechnicalStatus::FAILED->value, $vpsSubscription->children->firstOrFail()->technical_status);
    }

    #[Test]
    public function handleFailedJobWithInvalidSubscription(): void
    {
        $customer = CustomerFactory::new()->createOne();

        $vpsSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->vps())
            ->has(new SubscriptionFactory()->for(new ProductFactory()->ubuntu())->for($customer), 'children')
            ->createOne(['technical_status' => TechnicalStatus::PENDING->value]);

        $subscriptionRepository = self::createMock(SubscriptionRepository::class);
        $subscriptionRepository
            ->expects(self::once())
            ->method('getByUuid')
            ->with($vpsSubscription->uuid)
            ->willReturn(null);

        $mockVpsService = self::createMock(VpsService::class);
        $mockVpsService->expects(self::never())->method('create');
        $expectedException = new Exception('Test exception');

        $mockLogger = self::mock(LoggerInterface::class);

        $mockLogger
            ->shouldReceive('error')
            ->once()
            ->with(
                'Failed to create VPS',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $vpsSubscription->uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProductGroupType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => 'cloudstack',
                    LoggingContextKeys::EXCEPTION => $expectedException,
                ],
            );

        $mockLogger
            ->shouldReceive('error')
            ->once()
            ->with(
                'Failed to update VPS subscriptions status, subscription not found with uuid {subscription.uuid}',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $vpsSubscription->uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProductGroupType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => 'cloudstack',
                    LoggingContextKeys::EXCEPTION => $expectedException,
                ],
            );

        $createEvent = new CreateVps(
            subscriptionUuid: $vpsSubscription->uuid,
            sshKeyUuid: null,
        );

        $listener = new VpsCreationListener($mockVpsService, $mockLogger, $subscriptionRepository);
        $listener->failed($createEvent, $expectedException);
    }
}
