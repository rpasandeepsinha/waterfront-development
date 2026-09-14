<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Subscriptions\Services\ServicePlanChecker;

#[CoversClass(ServicePlanChecker::class)]
class ServicePlanCheckerTest extends IntegrationTestCase
{
    private ServicePlanChecker $servicePlanChecker;

    public function setUp(): void
    {
        parent::setUp();
        $this->servicePlanChecker = self::resolve(ServicePlanChecker::class);
    }

    #[Test]
    public function hostingSubscriptionWithPaidServicePlusWillReturnTrue(): void
    {
        $customer = CustomerFactory::new()->createOne();
        $productGroupAddon = new ProductGroupFactory()->addon()->createOne();
        $productAddon = new ProductFactory()->for($productGroupAddon)->createOne();

        $productSpecHasServicePlus = ProductSpecFactory::new()->createOne(
            [
                'name' => ProductSpecName::HAS_SERVICE_PLUS,
                'value' => '1',
                'product_id' => $productAddon->id,
            ],
        );

        $productAddon->productSpecs()->save($productSpecHasServicePlus);

        $productGroupHosting = ProductGroupFactory::new()->hosting()->createOne();
        $productHosting = ProductFactory::new()->for($productGroupHosting)->createOne();

        $subscriptionHosting = SubscriptionFactory::new()
            ->administrativeStatusActive()
            ->for($customer)
            ->for($productHosting)
            ->createOne();
        SubscriptionFactory::new()
            ->administrativeStatusActive()
            ->for($customer)
            ->for($productAddon)
            ->createOne(
                ['parent_subscription_id' => $subscriptionHosting->id],
            );

        self::assertTrue($this->servicePlanChecker->isServicePlanActive($subscriptionHosting));
    }

    #[Test]
    public function inactiveSubscriptionWillReturnFalse(): void
    {
        $customer = CustomerFactory::new()->createOne();
        $productGroup = ProductGroupFactory::new()->addon()->createOne();
        $product = ProductFactory::new()->for($productGroup)->createOne();

        $productSpecHasServicePlus = ProductSpecFactory::new()->createOne(
            [
                'name' => ProductSpecName::HAS_SERVICE_PLUS,
                'value' => '1',
                'product_id' => $product->id,
            ],
        );

        $product->productSpecs()->save($productSpecHasServicePlus);
        $subscription = SubscriptionFactory::new()
            ->administrativeStatusArchived()
            ->for($customer)
            ->for($product)
            ->createOne();
        self::assertFalse($this->servicePlanChecker->isServicePlanActive($subscription));
    }

    #[Test]
    public function hostingSubscriptionWithoutFreeServicePlusProductSpecWillReturnFalse(): void
    {
        $customer = CustomerFactory::new()->createOne();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        $subscription = SubscriptionFactory::new()
            ->administrativeStatusActive()
            ->for($customer)
            ->for($product)
            ->createOne();
        self::assertFalse($this->servicePlanChecker->isServicePlanActive($subscription));
    }

    /**
     * @return array<string, list<bool|int|string>>
     */
    public static function hostingSpecValueDataProvider(): array
    {
        return [
            'boolean:true will return true' => [true, true],
            'boolean:false will return false' => [false, false],
            'string:true will return true' => ['true', true],
            'string:false will return false' => ['false', false],
            'string:yes will return true' => ['yes', true],
            'string:no will return false' => ['no', false],
            'string:1 will return true' => ['1', true],
            'string:0 will return false' => ['0', false],
            'integer:1 will return true' => [1, true],
            'integer:0 will return false' => [0, false],
        ];
    }

    #[Test]
    #[DataProvider('hostingSpecValueDataProvider')]
    public function hostingSubscriptionWithValueForFreeServicePlusProductSpecWillReturnAsExpected(
        mixed $value,
        bool $expected,
    ): void {
        $customer = CustomerFactory::new()->createOne();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        $productSpecHasServicePlus = ProductSpecFactory::new()->createOne(
            [
                'name' => ProductSpecName::HAS_SERVICE_PLUS,
                'value' => $value,
                'product_id' => $product->id,
            ],
        );

        $product->productSpecs()->save($productSpecHasServicePlus);

        $subscription = SubscriptionFactory::new()
            ->administrativeStatusActive()
            ->for($customer)
            ->for($product)
            ->createOne();

        if ($expected) {
            self::assertTrue($this->servicePlanChecker->isServicePlanActive($subscription));
        } else {
            self::assertFalse($this->servicePlanChecker->isServicePlanActive($subscription));
        }
    }

    #[Test]
    public function customerHasAccessToServicePlan(): void
    {
        $customer = CustomerFactory::new()->createOne();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        $productSpecHasServicePlus = ProductSpecFactory::new()->createOne(
            [
                'name' => ProductSpecName::HAS_SERVICE_PLUS,
                'value' => '1',
                'product_id' => $product->id,
            ],
        );

        $product->productSpecs()->save($productSpecHasServicePlus);

        SubscriptionFactory::new()->administrativeStatusActive()->for($customer)->for($product)->createOne();

        self::assertTrue($this->servicePlanChecker->hasAccessToServicePlan($customer));

        $productSpecHasServicePlus->forceDelete();

        self::assertFalse($this->servicePlanChecker->hasAccessToServicePlan($customer));
    }

    #[Test]
    public function customerDoesntHaveAccessToServicePlanWithoutActiveSubscription(): void
    {
        $customer = CustomerFactory::new()->createOne();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        $productSpecHasServicePlus = ProductSpecFactory::new()->createOne(
            [
                'name' => ProductSpecName::HAS_SERVICE_PLUS,
                'value' => '1',
                'product_id' => $product->id,
            ],
        );

        $product->productSpecs()->save($productSpecHasServicePlus);

        SubscriptionFactory::new()->administrativeStatusExpired()->for($customer)->for($product)->createOne();

        self::assertFalse($this->servicePlanChecker->hasAccessToServicePlan($customer));
    }

    #[Test]
    public function customerCanOrderServicePlan(): void
    {
        $customer = CustomerFactory::new()->createOne();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $productWithoutServicePlus = new ProductFactory()->for($productGroup)->createOne();
        $productWithServicePlus = new ProductFactory()->for($productGroup)->createOne();

        $productSpecHasServicePlus = ProductSpecFactory::new()->createOne(
            [
                'name' => ProductSpecName::HAS_SERVICE_PLUS,
                'value' => '1',
                'product_id' => $productWithServicePlus->id,
            ],
        );

        $productWithServicePlus->productSpecs()->save($productSpecHasServicePlus);

        SubscriptionFactory::new()
            ->administrativeStatusActive()
            ->for($customer)
            ->for($productWithoutServicePlus)
            ->createOne();

        self::assertTrue($this->servicePlanChecker->canOrderServicePlan($customer));
    }

    #[Test]
    public function customerCanNotOrderServicePlanWithoutSubscriptionsWithoutServicePlan(): void
    {
        $customer = CustomerFactory::new()->createOne();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        new ProductFactory()->for($productGroup)->createOne();
        $productWithServicePlus = new ProductFactory()->for($productGroup)->createOne();

        $productSpecHasServicePlus = ProductSpecFactory::new()->createOne(
            [
                'name' => ProductSpecName::HAS_SERVICE_PLUS,
                'value' => '1',
                'product_id' => $productWithServicePlus->id,
            ],
        );

        $productWithServicePlus->productSpecs()->save($productSpecHasServicePlus);

        SubscriptionFactory::new()
            ->administrativeStatusActive()
            ->for($customer)
            ->for($productWithServicePlus)
            ->createOne();

        self::assertFalse($this->servicePlanChecker->canOrderServicePlan($customer));
    }
}
