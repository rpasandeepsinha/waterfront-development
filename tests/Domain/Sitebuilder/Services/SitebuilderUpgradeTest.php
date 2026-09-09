<?php

declare(strict_types=1);

namespace Tests\Domain\Sitebuilder\Services;

use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Sitebuilder\Requests\UpdateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Results\BasekitSiteResult;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;

#[CoversClass(SitebuilderService::class)]
class SitebuilderUpgradeTest extends IntegrationTestCase
{
    private SitebuilderService $service;

    private ProvisionGateway&MockObject $gatewayMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gatewayMock = self::createMock(ProvisionGateway::class);
        $this->app->bind(ProvisionGateway::class, fn () => $this->gatewayMock);

        $this->service = self::resolve(SitebuilderService::class);
    }

    #[Test]
    public function success(): void
    {
        $customer = new CustomerFactory()->createOne();
        $sitebuilderProduct = new ProductFactory()->for(new ProductGroupFactory()->hosting())->siteBuilder()->createOne();
        ProductSpecFactory::new()->for($sitebuilderProduct)->createOne(['name' => ProductSpecName::BASEKIT_PACKAGE_REFERENCE, 'value' => 123]);
        $addonProduct = ProductFactory::new()->for(ProductGroupFactory::new()->addon()->createOne())->createOne();
        ProductSpecFactory::new()->for($addonProduct)->createOne(['name' => ProductSpecName::BASEKIT_PACKAGE_REFERENCE, 'value' => 1234]);

        $subscription = new SubscriptionFactory()->for($sitebuilderProduct)->for($customer)->createOne();
        new SubscriptionFactory()->for($addonProduct)->for($customer)->createOne(['parent_subscription_id' => $subscription->id]);

        $gatewayResult = new BasekitSiteResult(
            provisionData: self::createStub(ProvisionRequestInterface::class),
            provisionStatus: ProvisionStatus::SUCCESS,
            siteRef: 5678,
            domain: 'example.com',
        );

        $this->gatewayMock->expects($this->once())
            ->method('request')
            ->with(self::callback(function (UpdateSitebuilderRequest $request) use ($subscription) {
                self::assertEqualsCanonicalizing([123, 1234], $request->packages);
                self::assertSame($subscription->uuid, $request->context->toString());
                self::assertSame($subscription->uuid, $request->tag->toString());
                self::assertSame($subscription->contract_period, $request->contractPeriod);
                return true;
            }))
            ->willReturn($gatewayResult);

        $this->service->update($subscription);
    }

    #[Test]
    public function provisioningFailedShouldThrowException(): void
    {
        $customer = new CustomerFactory()->createOne();
        $sitebuilderProduct = new ProductFactory()->for(new ProductGroupFactory()->hosting())->siteBuilder()->createOne();
        ProductSpecFactory::new()->for($sitebuilderProduct)->createOne(['name' => ProductSpecName::BASEKIT_PACKAGE_REFERENCE, 'value' => 123]);

        $subscription = new SubscriptionFactory()->for($sitebuilderProduct)->for($customer)->createOne();

        $gatewayResult = new BasekitSiteResult(
            provisionData: self::createStub(ProvisionRequestInterface::class),
            provisionStatus: ProvisionStatus::FAILED,
            siteRef: 5678,
            domain: 'example.com',
        );

        $this->gatewayMock->expects($this->once())
            ->method('request')
            ->willReturn($gatewayResult);

        $originalStatus = $subscription->technical_status;

        $this->expectException(Exception::class);

        try {
            $this->service->update($subscription);
        } finally {
            $subscription->refresh();
            self::assertSame($originalStatus, $subscription->technical_status);
            self::assertNotSame(TechnicalStatus::FAILED->value, $subscription->technical_status);
        }
    }
}
