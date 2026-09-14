<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Actions;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
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
use Waterfront\Domain\DNS\Actions\ChangeDnsAction;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Services\HostingDowngradeExecutor;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Domain\Subscriptions\Actions\DowngradeSubscriptionsAction;
use Waterfront\Domain\Subscriptions\DTO\SubscriptionChangeResult;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Support\Exceptions\NotImplementedException;

#[CoversClass(DowngradeSubscriptionsAction::class)]
#[AllowMockObjectsWithoutExpectations]
class DowngradeSubscriptionsActionTest extends IntegrationTestCase
{
    private ChangeDnsAction&MockObject $changeDnsAction;

    private SitebuilderService&MockObject $mockSitebuilderService;

    private ProductSpecRepository&MockObject $mockSpecRepository;

    private HostingDowngradeExecutor&MockObject $mockHostingDowngradeExecutor;

    private LoggerInterface&MockObject $logger;

    private DowngradeSubscriptionsAction $downgradeSubscriptionsAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->changeDnsAction = self::createMock(ChangeDnsAction::class);
        $this->mockSitebuilderService = self::createMock(SitebuilderService::class);
        $this->mockSpecRepository = self::createMock(ProductSpecRepository::class);
        $this->mockHostingDowngradeExecutor = self::createMock(HostingDowngradeExecutor::class);
        $this->logger = self::createMock(LoggerInterface::class);

        $this->downgradeSubscriptionsAction = new DowngradeSubscriptionsAction(
            changeDnsAction: $this->changeDnsAction,
            productSpecRepository: $this->mockSpecRepository,
            sitebuilderService: $this->mockSitebuilderService,
            hostingDowngradeExecutor: $this->mockHostingDowngradeExecutor,
            logger: $this->logger,
        );
    }

    #[Test]
    public function downgradeDnsProduct(): void
    {
        $group = ProductGroupFactory::new()->dns()->createOne();
        $premiumDns = ProductFactory::new()->premiumDns($group)->createOne();
        $freeDns = ProductFactory::new()->freeDns($group)->createOne();
        $subscription = SubscriptionFactory::new()->withCustomer()->for($premiumDns)->createOne();

        $this->changeDnsAction
            ->expects(self::once())
            ->method('execute')
            ->with($subscription, ProductChangeType::DOWNGRADE);

        $result = $this->downgradeSubscriptionsAction->execute($subscription, $freeDns);

        self::assertSame(SubscriptionChangeResult::STATUS_OK, $result->status);
    }

    #[Test]
    public function downgradeDnsProductReturnsErrorWhenChangeFails(): void
    {
        $group = ProductGroupFactory::new()->dns()->createOne();
        $premiumDns = ProductFactory::new()->premiumDns($group)->createOne();
        $freeDns = ProductFactory::new()->freeDns($group)->createOne();
        $subscription = SubscriptionFactory::new()->withCustomer()->for($premiumDns)->createOne();

        $exception = new RuntimeException('PowerDNS zone not found', 42);

        $this->changeDnsAction
            ->expects(self::once())
            ->method('execute')
            ->with($subscription, ProductChangeType::DOWNGRADE)
            ->willThrowException($exception);

        $this->logger->expects(self::once())->method('warning');

        $result = $this->downgradeSubscriptionsAction->execute($subscription, $freeDns);

        self::assertSame(SubscriptionChangeResult::STATUS_ERROR, $result->status);
        self::assertSame(42, $result->errorCode);
        self::assertSame('PowerDNS zone not found', $result->errorMessage);
    }

    #[Test]
    public function hostingDowngradeToMailWillChangeProperties(): void
    {
        $group = ProductGroupFactory::new()->hosting()->createOne();
        $hostingGold = ProductFactory::new()->hostingGold($group)->createOne();
        $hostingBronze = ProductFactory::new()->hostingBrons($group)->createOne();
        $subscription = SubscriptionFactory::new()
            ->withCustomer()
            ->has(HostingDeploymentFactory::new()->withPleskProvider())
            ->for($hostingGold)
            ->createOne();

        $hostingDeployment = $subscription->hostingDeployment;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);

        $currentServerId = $hostingDeployment->server_id;
        $currentProviderId = $hostingDeployment->provider_id;

        $this->mockSpecRepository
            ->expects(self::once())
            ->method('booleanSpecificationIsTrue')
            ->with($hostingBronze, ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER)
            ->willReturn(true);

        $this->mockHostingDowngradeExecutor->expects(self::never())->method('execute');

        $result = $this->downgradeSubscriptionsAction->execute($subscription, $hostingBronze);

        self::assertSame(SubscriptionChangeResult::STATUS_OK, $result->status);

        $hostingDeployment->refresh();
        self::assertNull($hostingDeployment->provider_id);
        self::assertNull($hostingDeployment->server_id);
        self::assertSame($currentProviderId, $hostingDeployment->mail_only_provider_id);
        self::assertSame($currentServerId, $hostingDeployment->mail_only_server_id);
    }

    #[Test]
    public function regularHostingDowngradeDelegatesToExecutorWithTheTargetProduct(): void
    {
        $group = ProductGroupFactory::new()->hosting()->createOne();
        $hostingGold = ProductFactory::new()->hostingGold($group)->createOne();
        $hostingBronze = ProductFactory::new()->hostingBrons($group)->createOne();
        $subscription = SubscriptionFactory::new()
            ->withCustomer()
            ->has(HostingDeploymentFactory::new()->withPleskProvider())
            ->for($hostingGold)
            ->createOne();

        $this->mockSpecRepository
            ->expects(self::once())
            ->method('booleanSpecificationIsTrue')
            ->with($hostingBronze, ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER)
            ->willReturn(false);

        $expectedResult = new SubscriptionChangeResult(status: SubscriptionChangeResult::STATUS_OK);

        $this->mockHostingDowngradeExecutor
            ->expects(self::once())
            ->method('execute')
            ->with(
                self::identicalTo($subscription),
                self::isInstanceOf(HostingDeployment::class),
                self::callback(static fn (Product $oldProduct): bool => $oldProduct->id === $hostingGold->id),
                self::identicalTo($hostingBronze),
            )
            ->willReturn($expectedResult);

        $result = $this->downgradeSubscriptionsAction->execute($subscription, $hostingBronze);

        self::assertSame($expectedResult, $result);

        $subscription->refresh();
        self::assertNotSame(TechnicalStatus::FAILED->value, $subscription->technical_status);
    }

    #[Test]
    public function regularHostingDowngradeReturnsExecutorErrorWithoutFailingSubscription(): void
    {
        $group = ProductGroupFactory::new()->hosting()->createOne();
        $hostingGold = ProductFactory::new()->hostingGold($group)->createOne();
        $hostingBronze = ProductFactory::new()->hostingBrons($group)->createOne();
        $subscription = SubscriptionFactory::new()
            ->withCustomer()
            ->has(HostingDeploymentFactory::new()->withPleskProvider())
            ->for($hostingGold)
            ->createOne();

        $originalStatus = $subscription->technical_status;

        $this->mockSpecRepository
            ->expects(self::once())
            ->method('booleanSpecificationIsTrue')
            ->with($hostingBronze, ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER)
            ->willReturn(false);

        $this->mockHostingDowngradeExecutor
            ->expects(self::once())
            ->method('execute')
            ->willReturn(new SubscriptionChangeResult(
                status: SubscriptionChangeResult::STATUS_ERROR,
                errorMessage: 'Disk usage (2000) exceeds quota (1000)',
            ));

        $result = $this->downgradeSubscriptionsAction->execute($subscription, $hostingBronze);

        self::assertSame(SubscriptionChangeResult::STATUS_ERROR, $result->status);
        self::assertSame('Disk usage (2000) exceeds quota (1000)', $result->errorMessage);

        $subscription->refresh();
        self::assertSame($originalStatus, $subscription->technical_status);
        self::assertNotSame(TechnicalStatus::FAILED->value, $subscription->technical_status);
    }

    #[Test]
    public function downgradeSitebuilderProduct(): void
    {
        $group = ProductGroupFactory::new()->hosting()->createOne();
        $sitebuilderShop = ProductFactory::new()->sitebuilderShop($group)->createOne();
        $sitebuilder = ProductFactory::new()->siteBuilder($group)->createOne();
        $subscription = SubscriptionFactory::new()
            ->withCustomer()
            ->has(HostingDeploymentFactory::new()->withMailOnlyProvider())
            ->for($sitebuilderShop)
            ->createOne();

        $this->mockSpecRepository
            ->expects(self::once())
            ->method('booleanSpecificationIsTrue')
            ->with($sitebuilder, ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER)
            ->willReturn(false);

        $this->mockSitebuilderService
            ->expects(self::once())
            ->method('hasSitebuilderThroughGateway')
            ->with($subscription->customer->email)
            ->willReturn(true);

        $this->mockSitebuilderService->expects(self::once())->method('update')->with($subscription);

        $this->mockHostingDowngradeExecutor->expects(self::never())->method('execute');

        $result = $this->downgradeSubscriptionsAction->execute($subscription, $sitebuilder);

        self::assertSame(SubscriptionChangeResult::STATUS_OK, $result->status);
    }

    #[Test]
    public function downgradeSitebuilderProductReturnsErrorWhenUpdateFails(): void
    {
        $group = ProductGroupFactory::new()->hosting()->createOne();
        $sitebuilderShop = ProductFactory::new()->sitebuilderShop($group)->createOne();
        $sitebuilder = ProductFactory::new()->siteBuilder($group)->createOne();
        $subscription = SubscriptionFactory::new()
            ->withCustomer()
            ->has(HostingDeploymentFactory::new()->withMailOnlyProvider())
            ->for($sitebuilderShop)
            ->createOne();

        $originalStatus = $subscription->technical_status;

        $this->mockSpecRepository
            ->expects(self::once())
            ->method('booleanSpecificationIsTrue')
            ->with($sitebuilder, ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER)
            ->willReturn(false);

        $this->mockSitebuilderService
            ->expects(self::once())
            ->method('hasSitebuilderThroughGateway')
            ->with($subscription->customer->email)
            ->willReturn(true);

        $this->mockSitebuilderService
            ->expects(self::once())
            ->method('update')
            ->with($subscription)
            ->willThrowException(new RuntimeException('Sitebuilder provision failed'));

        $this->logger->expects(self::once())->method('warning');

        $result = $this->downgradeSubscriptionsAction->execute($subscription, $sitebuilder);

        self::assertSame(SubscriptionChangeResult::STATUS_ERROR, $result->status);

        $subscription->refresh();
        self::assertSame($originalStatus, $subscription->technical_status);
        self::assertNotSame(TechnicalStatus::FAILED->value, $subscription->technical_status);
    }

    #[Test]
    public function notImplementedException(): void
    {
        $group = ProductGroupFactory::new()->extension()->createOne();
        $product = ProductFactory::new()->for($group)->createOne();
        $subscription = SubscriptionFactory::new()->withCustomer()->for($product)->createOne();

        self::expectException(NotImplementedException::class);
        $this->downgradeSubscriptionsAction->execute($subscription, $product);
    }
}
