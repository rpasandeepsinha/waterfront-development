<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Products\Models\ProductAddonCoupling;
use Waterfront\Domain\Subscriptions\Services\SubscriptionAddonService;

#[CoversClass(SubscriptionAddonService::class)]
class SubscriptionAddonServiceTest extends IntegrationTestCase
{
    private SubscriptionAddonService $subscriptionAddonAdditionService;

    protected function setUp(): void
    {
        parent::setUp();
        Model::preventLazyLoading(false);

        $this->subscriptionAddonAdditionService = self::resolve(SubscriptionAddonService::class);
    }

    #[Test]
    public function getPotentialAddonsReturnsAddonsNotAlreadyActive(): void
    {
        $customer = CustomerFactory::new()->createOne();

        $productGroup = ProductGroupFactory::new()->hosting()->createOne();
        $addonGroup = ProductGroupFactory::new()->addon()->createOne();

        $product = ProductFactory::new()->for($productGroup)->createOne();
        $addonProduct = ProductFactory::new()->for($addonGroup)->createOne();
        $alreadyBoughAddonProduct = ProductFactory::new()->for($addonGroup)->createOne();

        ProductPriceComponentFactory::new()->for($addonProduct)->registration()->createOne();

        $addonCoupling = new ProductAddonCoupling();
        $addonCoupling->parent_product_id = $product->id;
        $addonCoupling->addon_product_id = $addonProduct->id;
        $addonCoupling->save();

        $addonCoupling = new ProductAddonCoupling();
        $addonCoupling->parent_product_id = $product->id;
        $addonCoupling->addon_product_id = $alreadyBoughAddonProduct->id;
        $addonCoupling->save();

        $subscription = SubscriptionFactory::new()->administrativeStatusActive()->for($customer)->for($product)->createOne();
        SubscriptionFactory::new()->administrativeStatusActive()->for($customer)->for($alreadyBoughAddonProduct)->createOne(['parent_subscription_id' => $subscription->id]);

        $addons = $this->subscriptionAddonAdditionService->getPotentialAddons($subscription);

        self::assertCount(1, $addons);
        self::assertIsArray($addons->firstOrFail()['product']);
        self::assertSame($addonProduct->id, $addons->firstOrFail()['product']['id']);
    }

    #[Test]
    public function getPotentialAddonsReturnsProRataPrice(): void
    {
        $startDate = CarbonImmutable::create(2024, 7, 2);
        $customer = CustomerFactory::new()->createOne();

        $productGroup = ProductGroupFactory::new()->hosting()->createOne();
        $addonGroup = ProductGroupFactory::new()->addon()->createOne();

        $product = ProductFactory::new()->for($productGroup)->createOne();
        $addonProduct = ProductFactory::new()->for($addonGroup)->createOne();

        ProductPriceComponentFactory::new()->for($addonProduct)->registration()->createOne(['price' => 1200, 'starts_at' => $startDate]);

        $addonCoupling = new ProductAddonCoupling();
        $addonCoupling->parent_product_id = $product->id;
        $addonCoupling->addon_product_id = $addonProduct->id;
        $addonCoupling->save();

        $subscription = SubscriptionFactory::new()
            ->administrativeStatusActive()
            ->for($customer)
            ->for($product)
            ->createOne([
                'start_date' => '2024-01-01',
                'end_date' => '2025-01-01',
                'next_billing_date' => '2025-01-01',
                'contract_period' => 12,
                'billing_period' => 12,
            ]);

        CarbonImmutable::setTestNow($startDate);

        $addons = $this->subscriptionAddonAdditionService->getPotentialAddons($subscription);

        self::assertIsArray($addons->firstOrFail()['product']);
        self::assertSame($addonProduct->id, $addons->firstOrFail()['product']['id']);
        self::assertSame(1200, $addons->firstOrFail()['full_charge']);

        // 1200 * (183 days remaining / 366 days) = 600
        self::assertSame(600, $addons->firstOrFail()['charge']);
    }

    #[Test]
    public function getPotentialAddonsReturnsEmptyCollectionForSitebuilderWithoutGatewayDeployment(): void
    {
        $customer = CustomerFactory::new()->createOne();

        $productGroup = ProductGroupFactory::new()->hosting()->createOne();
        $product = ProductFactory::new()->siteBuilder()->for($productGroup)->createOne();

        $addonGroup = ProductGroupFactory::new()->addon()->createOne();
        ProductFactory::new()->for($addonGroup)->createOne();

        $subscription = SubscriptionFactory::new()
            ->administrativeStatusActive()
            ->for($customer)
            ->for($product)
            ->createOne();

        $addons = $this->subscriptionAddonAdditionService->getPotentialAddons($subscription);

        self::assertCount(0, $addons);
    }
}
