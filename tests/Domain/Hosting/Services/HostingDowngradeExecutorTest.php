<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Actions\ChangeHostingAction;
use Waterfront\Domain\Hosting\DTO\DowngradeCheckResult;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Services\HostingDowngradeExecutor;
use Waterfront\Domain\Hosting\Services\HostingDowngradePossibilityChecker;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\DTO\SubscriptionChangeResult;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(HostingDowngradeExecutor::class)]
class HostingDowngradeExecutorTest extends IntegrationTestCase
{
    private HostingDowngradePossibilityChecker&MockObject $downgradePossibilityChecker;

    private ChangeHostingAction&MockObject $changeHostingAction;

    private LoggerInterface&MockObject $logger;

    private HostingDowngradeExecutor $executor;

    private Subscription $subscription;

    private Product $oldProduct;

    private Product $newProduct;

    private HostingDeployment $hostingDeployment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->downgradePossibilityChecker = self::createMock(HostingDowngradePossibilityChecker::class);
        $this->changeHostingAction = self::createMock(ChangeHostingAction::class);
        $this->logger = self::createMock(LoggerInterface::class);

        $this->executor = new HostingDowngradeExecutor(
            downgradePossibilityChecker: $this->downgradePossibilityChecker,
            changeHostingAction: $this->changeHostingAction,
            logger: $this->logger,
        );

        $group = ProductGroupFactory::new()->hosting()->createOne();
        $this->oldProduct = ProductFactory::new()->hostingGold($group)->createOne();
        $this->newProduct = ProductFactory::new()->hostingBrons($group)->createOne();
        $this->subscription = SubscriptionFactory::new()
            ->withCustomer()
            ->has(HostingDeploymentFactory::new()->withPleskProvider())
            ->for($this->oldProduct)
            ->createOne();

        $hostingDeployment = $this->subscription->hostingDeployment;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);
        $this->hostingDeployment = $hostingDeployment;
    }

    #[Test]
    public function executesServicePlanChangeWhenDowngradeIsPossible(): void
    {
        $this->downgradePossibilityChecker
            ->expects(self::once())
            ->method('canDowngradeToServicePlan')
            ->with(self::identicalTo($this->hostingDeployment), $this->newProduct->slug)
            ->willReturn(new DowngradeCheckResult(true, null));

        $expectedResult = new SubscriptionChangeResult(status: SubscriptionChangeResult::STATUS_OK);

        $this->changeHostingAction
            ->expects(self::once())
            ->method('execute')
            ->with(
                self::identicalTo($this->subscription),
                self::identicalTo($this->hostingDeployment),
                self::identicalTo($this->oldProduct),
                self::identicalTo($this->newProduct),
            )
            ->willReturn($expectedResult);

        $this->logger->expects(self::never())->method('warning');

        $result = $this->executor->execute(
            $this->subscription,
            $this->hostingDeployment,
            $this->oldProduct,
            $this->newProduct,
        );

        self::assertSame($expectedResult, $result);
    }

    #[Test]
    public function returnsErrorWithoutChangingServicePlanWhenUsageExceedsQuota(): void
    {
        $this->downgradePossibilityChecker
            ->expects(self::once())
            ->method('canDowngradeToServicePlan')
            ->with(self::identicalTo($this->hostingDeployment), $this->newProduct->slug)
            ->willReturn(new DowngradeCheckResult(false, 'Disk usage (2000) exceeds quota (1000)'));

        $this->changeHostingAction->expects(self::never())->method('execute');

        $this->logger->expects(self::never())->method('warning');

        $result = $this->executor->execute(
            $this->subscription,
            $this->hostingDeployment,
            $this->oldProduct,
            $this->newProduct,
        );

        self::assertSame(SubscriptionChangeResult::STATUS_ERROR, $result->status);
        self::assertSame('Disk usage (2000) exceeds quota (1000)', $result->errorMessage);
    }

    #[Test]
    public function returnsErrorAndLogsWarningWhenServicePlanChangeThrows(): void
    {
        $this->downgradePossibilityChecker
            ->expects(self::once())
            ->method('canDowngradeToServicePlan')
            ->willReturn(new DowngradeCheckResult(true, null));

        $this->changeHostingAction
            ->expects(self::once())
            ->method('execute')
            ->willThrowException(new RuntimeException('panel unreachable', 503));

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Technical hosting downgrade to product id {product.id} not performed for subscription {subscription.uuid}',
                self::callback(
                    fn (array $context): bool => (
                        ($context['subscription.uuid'] ?? null) === $this->subscription->uuid
                        && ($context['product.id'] ?? null) === $this->newProduct->id
                    ),
                ),
            );

        $result = $this->executor->execute(
            $this->subscription,
            $this->hostingDeployment,
            $this->oldProduct,
            $this->newProduct,
        );

        self::assertSame(SubscriptionChangeResult::STATUS_ERROR, $result->status);
        self::assertSame('panel unreachable', $result->errorMessage);
        self::assertSame(503, $result->errorCode);
    }
}
