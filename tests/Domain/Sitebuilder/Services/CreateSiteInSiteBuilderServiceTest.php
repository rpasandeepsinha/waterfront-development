<?php

declare(strict_types=1);

namespace Tests\Domain\Sitebuilder\Services;

use Exception;
use Illuminate\Config\Repository;
use Illuminate\Events\Dispatcher as EventDispatcher;
// @phpstan-ignore disallowed.namespace
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Events\UpdateDns;
use Waterfront\Domain\DNS\Services\DnsZoneService;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Products\Models\ProductAddonCoupling;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Results\AbstractProvisionResult;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\BasekitContextDeleteException;
use Waterfront\Domain\Provision\Sitebuilder\Requests\CreateSitebuilderRequest;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\Configuration\Configuration;

#[CoversClass(SitebuilderService::class)]
class CreateSiteInSiteBuilderServiceTest extends IntegrationTestCase
{
    #[Test]
    public function createSiteCallsGateway(): void
    {
        ProviderFactory::new()->createOne([
            'type' => ProviderType::SITEBUILDER,
            'enabled' => true,
            'default' => true,
            'slug' => ProviderSlug::BASEKIT,
        ]);

        ProviderFactory::new()->createOne([
            'type' => ProviderType::MAILONLY,
            'enabled' => true,
            'default' => true,
            'slug' => ProviderSlug::PLESK,
        ]);

        $mailServerIpv4 = '13.37.13.37';
        $mailServerIpv6 = '2001:0db8:85a3:0000:0000:8a2e:0370:7334';
        ServerFactory::new()->state([
            'ipv4' => $mailServerIpv4,
            'ipv6' => $mailServerIpv6,
        ])->createOne();

        $customer = CustomerFactory::new()->createOne(['email' => 'test@sandwave.io']);

        $product = ProductFactory::new()->for(ProductGroupFactory::new()->hosting()->createOne())->createOne([
            'slug' => ProductType::SITEBUILDER,
        ]);
        ProductSpecFactory::new()->for($product)->createOne([
            'name' => ProductSpecName::BASEKIT_PACKAGE_REFERENCE,
            'value' => 123,
        ]);

        $parentSub = SubscriptionFactory::new()->for($customer)->for($product)->createOne(['domain' => 'parent.com']);

        $addonProduct = ProductFactory::new()->for(ProductGroupFactory::new()->addon()->createOne())->createOne();
        ProductSpecFactory::new()->for($addonProduct)->createOne([
            'name' => ProductSpecName::BASEKIT_PACKAGE_REFERENCE,
            'value' => 1234,
        ]);

        $productAddonCoupling = new ProductAddonCoupling();
        $productAddonCoupling->parent_product_id = $product->id;
        $productAddonCoupling->addon_product_id = $addonProduct->id;
        $productAddonCoupling->save();

        $childSub = SubscriptionFactory::new()->for($customer)->for($addonProduct)->createOne([
            'parent_subscription_id' => $parentSub->id,
            'domain' => 'child.com',
        ]);

        $provisingGateway = self::createMock(ProvisionGateway::class);
        $this->app->bind(ProvisionGateway::class, fn (): ProvisionGateway => $provisingGateway);

        $eventDispatcher = self::createMock(EventDispatcher::class);
        $this->app->bind(EventDispatcher::class, fn (): EventDispatcher => $eventDispatcher);

        $dnsZoneService = self::createMock(DnsZoneService::class);
        $this->app->bind(DnsZoneService::class, fn (): DnsZoneService => $dnsZoneService);

        $resultMock = self::createStub(AbstractProvisionResult::class);
        $resultMock->provisionStatus = ProvisionStatus::SUCCESS;

        $provisingGateway
            ->expects(self::once())
            ->method('request')
            ->with(self::callback(function (CreateSitebuilderRequest $request) use ($parentSub) {
                self::assertSame($parentSub->domain, $request->domain);
                self::assertEqualsCanonicalizing([123, 1234], $request->packages);
                self::assertSame($parentSub->customer->first_name, $request->firstname);
                self::assertSame($parentSub->customer->last_name, $request->lastname);
                self::assertSame($parentSub->customer->email, $request->email);
                self::assertSame($parentSub->contract_period, $request->contractPeriod);
                self::assertSame($parentSub->uuid, $request->context->toString());

                return true;
            }))
            ->willReturn($resultMock);

        $basekitServerIpv4 = '73.31.73.31';

        // @phpstan-ignore disallowed.namespace
        $config = Mockery::mock(Configuration::class, [$this->app->make(Repository::class)])->makePartial();
        $this->app->bind(Configuration::class, fn (): Configuration => $config);

        $config->expects('getAsString')->once()->with('basekit.ipv4')->andReturn($basekitServerIpv4);

        $config->expects('getAsString')->never()->with('basekit.ipv6');

        $eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(fn (UpdateDns $event) => $event->getDomain() === $parentSub->domain));

        $dnsZoneService
            ->expects(self::once())
            ->method('getExternalHostingDnsRecords')
            ->with($parentSub->domain, $basekitServerIpv4, null, $mailServerIpv4, $mailServerIpv6);

        $sitebuilderService = $this->resolve(SitebuilderService::class);
        $sitebuilderService->createSite($parentSub);

        $parentSub->refresh();
        $childSub->refresh();

        self::assertSame(TechnicalStatus::OK->value, $parentSub->technical_status);
        self::assertSame(TechnicalStatus::OK->value, $childSub->technical_status);
    }

    #[Test]
    public function createSiteCallsGatewayAndFails(): void
    {
        ProviderFactory::new()->createOne([
            'type' => ProviderType::SITEBUILDER,
            'enabled' => true,
            'default' => true,
            'slug' => ProviderSlug::BASEKIT,
        ]);

        ProviderFactory::new()->createOne([
            'type' => ProviderType::MAILONLY,
            'enabled' => true,
            'default' => true,
            'slug' => ProviderSlug::PLESK,
        ]);

        ServerFactory::new()->createOne();
        $customer = CustomerFactory::new()->createOne(['email' => 'test@sandwave.io']);

        $product = ProductFactory::new()->for(ProductGroupFactory::new()->hosting()->createOne())->createOne([
            'slug' => ProductType::SITEBUILDER,
        ]);
        ProductSpecFactory::new()->for($product)->createOne([
            'name' => ProductSpecName::BASEKIT_PACKAGE_REFERENCE,
            'value' => 123,
        ]);

        $parentSub = SubscriptionFactory::new()->for($customer)->for($product)->createOne(['domain' => 'parent.com']);

        $addonProduct = ProductFactory::new()->for(ProductGroupFactory::new()->addon()->createOne())->createOne();
        ProductSpecFactory::new()->for($addonProduct)->createOne([
            'name' => ProductSpecName::BASEKIT_PACKAGE_REFERENCE,
            'value' => 1234,
        ]);

        $productAddonCoupling = new ProductAddonCoupling();
        $productAddonCoupling->parent_product_id = $product->id;
        $productAddonCoupling->addon_product_id = $addonProduct->id;
        $productAddonCoupling->save();

        $childSub = SubscriptionFactory::new()->for($customer)->for($addonProduct)->createOne([
            'parent_subscription_id' => $parentSub->id,
            'domain' => 'child.com',
        ]);

        $provisingGateway = self::createMock(ProvisionGateway::class);
        $this->app->bind(ProvisionGateway::class, fn (): ProvisionGateway => $provisingGateway);

        $resultMock = self::createStub(AbstractProvisionResult::class);
        $resultMock->provisionStatus = ProvisionStatus::FAILED;
        $resultMock->exception = new BasekitContextDeleteException('Provisioning failed');

        $provisingGateway
            ->expects(self::once())
            ->method('request')
            ->with(self::callback(function (CreateSitebuilderRequest $request) use ($parentSub) {
                self::assertSame($parentSub->domain, $request->domain);
                self::assertEqualsCanonicalizing([123, 1234], $request->packages);
                self::assertSame($parentSub->customer->first_name, $request->firstname);
                self::assertSame($parentSub->customer->last_name, $request->lastname);
                self::assertSame($parentSub->customer->email, $request->email);
                self::assertSame($parentSub->contract_period, $request->contractPeriod);
                self::assertSame($parentSub->uuid, $request->context->toString());

                return true;
            }))
            ->willReturn($resultMock);

        $sitebuilderService = $this->resolve(SitebuilderService::class);
        $this->expectException(Exception::class);
        $sitebuilderService->createSite($parentSub);

        self::assertSame(TechnicalStatus::FAILED->value, $parentSub->technical_status);
        self::assertSame(TechnicalStatus::FAILED->value, $childSub->technical_status);
    }
}
