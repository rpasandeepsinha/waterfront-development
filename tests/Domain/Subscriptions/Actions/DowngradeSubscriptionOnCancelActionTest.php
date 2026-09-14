<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Actions;

use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Actions\ChangeDnsAction;
use Waterfront\Domain\DNS\Exceptions\DnsChangeException;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Subscriptions\Actions\DowngradeSubscriptionOnCancelAction;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Exceptions\DowngradeCancelException;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionChangeException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionChangeService;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(DowngradeSubscriptionOnCancelAction::class)]
class DowngradeSubscriptionOnCancelActionTest extends IntegrationTestCase
{
    private SubscriptionChangeService&MockObject $subscriptionChangeServiceMock;

    private ChangeDnsAction&MockObject $changeDnsActionMock;

    private LoggerInterface&MockObject $loggerMock;

    private DowngradeSubscriptionOnCancelAction $action;

    private Subscription $subscription;

    private ProductGroup $productGroup;

    /**
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->productGroup = new ProductGroupFactory()->hosting()->createOne();

        $cancelProduct = new ProductFactory()->for($this->productGroup)->createOne();

        $this->subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($cancelProduct)
            ->createOne();

        $this->subscriptionChangeServiceMock = $this->createMock(SubscriptionChangeService::class);
        $this->changeDnsActionMock = $this->createMock(ChangeDnsAction::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->action = new DowngradeSubscriptionOnCancelAction(
            subscriptionChangeService: $this->subscriptionChangeServiceMock,
            changeDnsAction: $this->changeDnsActionMock,
            logger: $this->loggerMock,
        );
    }

    #[Test]
    public function execute(): void
    {
        $this->loggerMock->expects(self::never())->method('error');

        $downGradeTargetProduct = ProductFactory::new()->for($this->productGroup)->create();

        $this->subscriptionChangeServiceMock
            ->expects(self::once())
            ->method('getAvailableDowngradeWhenCanceled')
            ->with($this->subscription)
            ->willReturn($downGradeTargetProduct);

        $this->changeDnsActionMock
            ->expects(self::once())
            ->method('execute')
            ->with(
                $this->subscription,
                ProductChangeType::DOWNGRADE,
            );

        $this->subscriptionChangeServiceMock
            ->expects(self::once())
            ->method('change')
            ->with(
                ProductChangeType::DOWNGRADE,
                $this->subscription,
                $downGradeTargetProduct,
            );

        $this->action->execute($this->subscription);
    }

    #[Test]
    public function executeFailedReceivingProduct(): void
    {
        $exception = new DowngradeCancelException();
        $this->subscriptionChangeServiceMock
            ->expects(self::once())
            ->method('getAvailableDowngradeWhenCanceled')
            ->with($this->subscription)
            ->willThrowException($exception);

        $this->changeDnsActionMock->expects(self::never())->method('execute');

        $this->subscriptionChangeServiceMock->expects(self::never())->method('change');

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Tried to downgrade subscription with uuid: {subscription.uuid} while canceled',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

        $this->action->execute($this->subscription);
    }

    #[Test]
    public function executeFailedDnsAction(): void
    {
        $downGradeTargetProduct = ProductFactory::new()->for($this->productGroup)->create();

        $this->subscriptionChangeServiceMock
            ->expects(self::once())
            ->method('getAvailableDowngradeWhenCanceled')
            ->with($this->subscription)
            ->willReturn($downGradeTargetProduct);

        $exception = new DnsChangeException();
        $this->changeDnsActionMock
            ->expects(self::once())
            ->method('execute')
            ->with(
                $this->subscription,
                ProductChangeType::DOWNGRADE,
            )
            ->willThrowException($exception);

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Tried to downgrade subscription with uuid: {subscription.uuid} while canceled',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

        $this->subscriptionChangeServiceMock->expects(self::never())->method('change');

        $this->action->execute($this->subscription);
    }

    #[Test]
    public function executeFailedDnsActionNoCatchException(): void
    {
        $downGradeTargetProduct = ProductFactory::new()->for($this->productGroup)->create();

        $this->subscriptionChangeServiceMock
            ->expects(self::once())
            ->method('getAvailableDowngradeWhenCanceled')
            ->with($this->subscription)
            ->willReturn($downGradeTargetProduct);

        $exception = new Exception();
        $this->changeDnsActionMock
            ->expects(self::once())
            ->method('execute')
            ->with(
                $this->subscription,
                ProductChangeType::DOWNGRADE,
            )
            ->willThrowException($exception);

        $this->loggerMock->expects(self::never())->method('error');

        $this->subscriptionChangeServiceMock->expects(self::never())->method('change');

        $this->expectException(Exception::class);

        $this->action->execute($this->subscription);
    }

    #[Test]
    public function executeFailedSubscriptionChange(): void
    {
        $downGradeTargetProduct = ProductFactory::new()->for($this->productGroup)->create();

        $this->subscriptionChangeServiceMock
            ->expects(self::once())
            ->method('getAvailableDowngradeWhenCanceled')
            ->with($this->subscription)
            ->willReturn($downGradeTargetProduct);

        $this->changeDnsActionMock
            ->expects(self::once())
            ->method('execute')
            ->with(
                $this->subscription,
                ProductChangeType::DOWNGRADE,
            );

        $exception = new SubscriptionChangeException();
        $this->subscriptionChangeServiceMock
            ->expects(self::once())
            ->method('change')
            ->with(
                ProductChangeType::DOWNGRADE,
                $this->subscription,
                $downGradeTargetProduct,
            )
            ->willThrowException($exception);

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Tried to downgrade subscription with uuid: {subscription.uuid} while canceled',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

        $this->action->execute($this->subscription);
    }
}
