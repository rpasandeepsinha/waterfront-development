<?php

declare(strict_types=1);

namespace Tests\Apps\API\Atlantis\Controllers\OrderControllerTest;

use Illuminate\Routing\UrlGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\OrderController;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\ProductAddonCoupling;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;

#[CoversClass(OrderController::class)]
class OrderBasekitTest extends IntegrationTestCase
{
    #[Test]
    public function orderBasekitWithAddon(): void
    {
        $dnsProduct = new ProductFactory()->freeDns()->createOne();
        ProductSpecFactory::new()
            ->enable(ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT)
            ->for($dnsProduct)
            ->create();
        new ProductPriceComponentFactory()
            ->for($dnsProduct)
            ->registration()
            ->createOne(['price' => 0]);
        new ProductPriceComponentFactory()->for($dnsProduct)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'price' => 0,
        ]);
        $addonGroup = new ProductGroupFactory()->addon()->createOne();

        $hostingGroup = new ProductGroupFactory()->hosting()->createOne();
        $sitebuilderProduct = new ProductFactory()->for($hostingGroup)->createOne([
            'slug' => 'sitebuilder',
        ]);
        new ProductPriceComponentFactory()
            ->for($sitebuilderProduct)
            ->registration()
            ->createOne(['price' => 120]);
        new ProductPriceComponentFactory()->for($sitebuilderProduct)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'price' => 96,
        ]);

        $bookinAddon = new ProductFactory()->for($addonGroup)->createOne([
            'slug' => 'booking',
        ]);
        new ProductPriceComponentFactory()
            ->for($bookinAddon)
            ->registration()
            ->createOne(['price' => 120]);
        new ProductPriceComponentFactory()->for($bookinAddon)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'price' => 96,
        ]);

        $productSpec = new ProductSpec();
        $productSpec->name = ProductSpecName::BASEKIT_PACKAGE_REFERENCE->value;
        $productSpec->value = 0001;
        $productSpec->product_id = $sitebuilderProduct->id;
        $productSpec->save();

        $productSpec = new ProductSpec();
        $productSpec->name = ProductSpecName::BASEKIT_PACKAGE_REFERENCE->value;
        $productSpec->value = 6969;
        $productSpec->product_id = $bookinAddon->id;
        $productSpec->save();

        $siteBuilderBookingCoupling = new ProductAddonCoupling();
        $siteBuilderBookingCoupling->parent_product_id = $sitebuilderProduct->id;
        $siteBuilderBookingCoupling->addon_product_id = $bookinAddon->id;
        $siteBuilderBookingCoupling->save();

        new ServerFactory()->createOne();
        ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'enabled' => true,
            'default' => true,
        ]);
        ProviderFactory::new()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::PLESK,
            'enabled' => true,
            'default' => true,
        ]);
        ProviderFactory::new()->createOne([
            'type' => ProviderType::SSL,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'enabled' => true,
            'default' => true,
        ]);

        $jsonData = (string) file_get_contents(__DIR__ . '/data/order_payload_basekit_with_addon.json');

        /** @var array<mixed, mixed> $orderData */
        $orderData = json_decode($jsonData, associative: true, flags: JSON_THROW_ON_ERROR);

        $subscriptionService = self::createMock(SubscriptionService::class);
        $subscriptionService
            ->expects(self::once())
            ->method('dispatchProcessOrderJob')
            ->with(self::callback(function (Order $order) {
                self::assertCount(2, $order->lineItems);

                return true;
            }));

        $this->app->bind(SubscriptionService::class, fn () => $subscriptionService);

        $customer = new CustomerFactory()->withAddress()->createOne();

        $router = self::resolve(UrlGenerator::class);
        $this->actingAsCustomer($customer)->postJson($router->route('partners.order.order'), $orderData)->assertOk();
    }

    #[Test]
    public function orderBasekitWithAddonNotCoupledShouldFail(): void
    {
        $dnsProduct = new ProductFactory()->freeDns()->createOne();
        ProductSpecFactory::new()
            ->enable(ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT)
            ->for($dnsProduct)
            ->create();
        new ProductPriceComponentFactory()
            ->for($dnsProduct)
            ->registration()
            ->createOne(['price' => 0]);
        new ProductPriceComponentFactory()->for($dnsProduct)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'price' => 0,
        ]);
        $addonGroup = new ProductGroupFactory()->addon()->createOne();

        $hostingGroup = new ProductGroupFactory()->hosting()->createOne();
        $sitebuilderProduct = new ProductFactory()->for($hostingGroup)->createOne([
            'slug' => 'sitebuilder',
        ]);
        new ProductPriceComponentFactory()
            ->for($sitebuilderProduct)
            ->registration()
            ->createOne(['price' => 120]);
        new ProductPriceComponentFactory()->for($sitebuilderProduct)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'price' => 96,
        ]);

        $bookinAddon = new ProductFactory()->for($addonGroup)->createOne([
            'slug' => 'booking',
        ]);
        new ProductPriceComponentFactory()
            ->for($bookinAddon)
            ->registration()
            ->createOne(['price' => 120]);
        new ProductPriceComponentFactory()->for($bookinAddon)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'price' => 96,
        ]);

        $productSpec = new ProductSpec();
        $productSpec->name = ProductSpecName::BASEKIT_PACKAGE_REFERENCE->value;
        $productSpec->value = 0001;
        $productSpec->product_id = $sitebuilderProduct->id;
        $productSpec->save();

        $productSpec = new ProductSpec();
        $productSpec->name = ProductSpecName::BASEKIT_PACKAGE_REFERENCE->value;
        $productSpec->value = 6969;
        $productSpec->product_id = $bookinAddon->id;
        $productSpec->save();

        new ServerFactory()->createOne();
        ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'enabled' => true,
            'default' => true,
        ]);
        ProviderFactory::new()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::PLESK,
            'enabled' => true,
            'default' => true,
        ]);
        ProviderFactory::new()->createOne([
            'type' => ProviderType::SSL,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'enabled' => true,
            'default' => true,
        ]);

        $jsonData = (string) file_get_contents(__DIR__ . '/data/order_payload_basekit_with_addon.json');

        /** @var array<mixed, mixed> $orderData */
        $orderData = json_decode($jsonData, associative: true, flags: JSON_THROW_ON_ERROR);

        $customer = new CustomerFactory()->withAddress()->createOne();

        $router = self::resolve(UrlGenerator::class);
        $this->actingAsCustomer($customer)
            ->postJson($router->route('partners.order.order'), $orderData)
            ->assertUnprocessable();
    }
}
