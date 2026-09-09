<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\DataProvider\DomainSubscriptionDataProvider;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\OrderLinePrice;
use Waterfront\Domain\Pricing\Models\OrderLinePriceComponent;
use Waterfront\Domain\Pricing\Models\SubscriptionPrice;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\DTO\SubscriptionUpdateRequestDTO;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;

#[CoversClass(SubscriptionService::class)]
class SubscriptionServiceTest extends IntegrationTestCase
{
    private SubscriptionService $subscriptionService;

    public function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $dispatcher = self::createStub(Dispatcher::class);
        $this->app->bind(Dispatcher::class, fn () => $dispatcher);

        $this->subscriptionService = self::resolve(SubscriptionService::class);
    }

    #[Test]
    public function createSubscriptionsFromOrderWillCreateSubscription(): void
    {
        new ProviderFactory()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::PLESK, 'enabled' => true, 'default' => true]);
        new ServerFactory()->createOne();
        $order = new OrderFactory()->for(new CustomerFactory()->createOne())->createOne();
        $product = new ProductFactory()->for(new ProductGroupFactory()->hosting())->createOne();

        new ProductPriceComponentFactory()->for($product)->registration()->createOne(['price' => 96]);

        new OrderLineItemFactory()->withPrice()->for($order)->for($product)->createOne(['subscription_uuid' => null, 'status' => 'registration']);
        $this->subscriptionService->createSubscriptionsFromOrder($order);

        $order->refresh();

        $lineItem = $order->lineItems->firstOrFail();

        Assert::assertNotNull($lineItem->subscription_uuid);
    }

    #[Test]
    public function updateSubscription(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->hosting())->createOne();
        $subscription = new SubscriptionFactory()->for(new CustomerFactory()->createOne())->for($product)->createOne();

        $changeDTO = new SubscriptionUpdateRequestDTO(
            $product,
            'hallo.nl',
            AdministrativeStatus::CANCELED,
            TechnicalStatus::SUSPENDED,
            420,
            420
        );

        $this->subscriptionService->updateSubscription($subscription, $changeDTO);

        $subscription->refresh();

        Assert::assertSame(AdministrativeStatus::CANCELED->value, $subscription->administrative_status);
        Assert::assertSame(TechnicalStatus::SUSPENDED->value, $subscription->technical_status);
        Assert::assertSame(420, $subscription->net_price);
        Assert::assertSame(420, $subscription->gross_price);
        Assert::assertSame('hallo.nl', $subscription->domain);
    }

    #[Test]
    public function createSubscriptionsFromOrderWillCreateSubscriptionForMissingSubscription(): void
    {
        new ProviderFactory()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::PLESK, 'enabled' => true, 'default' => true]);
        new ServerFactory()->createOne();
        $order = new OrderFactory()->for(new CustomerFactory()->createOne())->createOne();
        $product = new ProductFactory()->for(new ProductGroupFactory()->hosting())->createOne();

        new ProductPriceComponentFactory()->for($product)->registration()->createOne(['price' => 96]);

        $domain = 'anyfreakydomain.nl';
        new OrderLineItemFactory()->withPrice()->for($order)->for($product)->createOne(['subscription_uuid' => 'invalid-uuid', 'status' => 'registration', 'domain' => $domain]);
        $this->subscriptionService->createSubscriptionsFromOrder($order);

        $order->refresh();

        $lineItem = $order->lineItems->firstOrFail();

        $subscription = Subscription::query()->where('domain', $domain)->firstOrFail();
        Assert::assertSame($subscription->uuid, $lineItem->subscription_uuid);
    }

    #[Test]
    public function createSubscriptionsFromOrderWithSubscriptionCreatedWillNotCreateSubscriptionAgain(): void
    {
        $order = new OrderFactory()->for(new CustomerFactory())->createOne();
        $product = new ProductFactory()->for(new ProductGroupFactory()->hosting()->createOne())->createOne();
        $subscription = new SubscriptionFactory()->for($product)->for((new CustomerFactory()))->createOne();

        $orderLineItem = new OrderLineItemFactory()->for($order)->for($product)->for($subscription)->createOne();
        $this->subscriptionService->createSubscriptionsFromOrder($order);

        $order->refresh();

        $lineItem = $order->lineItems->firstOrFail();

        Assert::assertSame($lineItem->subscription_uuid, $orderLineItem->subscription_uuid);
    }

    #[Test]
    public function orderlineProcessedAtFilledAfterSubscriptionCreation(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::now());
        $order = new OrderFactory()->for(new CustomerFactory())->createOne();
        $product = new ProductFactory()->for(new ProductGroupFactory()->hosting()->createOne())->createOne();
        new ProductPriceComponentFactory()->for($product)->registration()->createOne();

        new OrderLineItemFactory()->withPrice()->for($order)->for($product)->createOne();
        $this->subscriptionService->createSubscriptionsFromOrder($order);
        $order->refresh();

        $lineItem = $order->lineItems->firstOrFail();
        self::assertNotNull($lineItem->processed_at);
        self::assertSame($lineItem->processed_at, CarbonImmutable::now()->toDateTimeString());
    }

    #[Test]
    public function createSubscriptionsFromOrderExcludedGroup(): void
    {
        new ProviderFactory()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::PLESK, 'enabled' => true, 'default' => true]);
        new ServerFactory()->createOne();
        $order = new OrderFactory()->for(new CustomerFactory()->createOne())->createOne();
        $product = new ProductFactory()->for(new ProductGroupFactory()->createOne(['slug' => ProductGroupType::ONE_TIME_SERVICE]))->createOne();

        new ProductPriceComponentFactory()->for($product)->registration()->createOne(['price' => 96]);

        new OrderLineItemFactory()->for($order)->for($product)->createOne(['subscription_uuid' => null, 'status' => 'registration']);
        $this->subscriptionService->createSubscriptionsFromOrder($order);

        $lineItem = $order->lineItems->firstOrFail();

        Assert::assertNull($lineItem->subscription_uuid);
        Assert::assertSame(OrderStatus::IN_PROGRESS->value, $order->status->value);
    }

    #[Test]
    public function createSubscriptionsFromOrderWithParentOrderLinesCloudStack(): void
    {
        $order = new OrderFactory()->for(new CustomerFactory()->createOne())->createOne();
        $productParent = new ProductFactory()->for(new ProductGroupFactory()->cloudstackVirtualMachine())->createOne();

        new ProductPriceComponentFactory()->for($productParent)->registration()->createOne(['price' => 96]);

        $orderLineItemParent = new OrderLineItemFactory()->withPrice()->for($order)->for($productParent)->createOne(['subscription_uuid' => null, 'status' => 'registration']);
        $productChild = new ProductFactory()->for(new ProductGroupFactory()->createOne(['slug' => ProductGroupType::CLOUDSTACK_OS]))->createOne();

        new ProductPriceComponentFactory()->for($productChild)->registration()->createOne(['price' => 10]);

        new OrderLineItemFactory()->withPrice()->for($order)->for($productChild)->parentOrderLineItem($orderLineItemParent)->createOne(['subscription_uuid' => null, 'status' => 'registration']);

        $this->subscriptionService->createSubscriptionsFromOrder($order);

        $orderLineItemParent->refresh();

        Assert::assertNull($orderLineItemParent->parent_id);
        $parentSubscription = Subscription::where('uuid', $orderLineItemParent->subscription_uuid)->firstOrFail();
        Assert::assertNull($parentSubscription->parent_subscription_id);

        $lineItemChild = $orderLineItemParent->children->firstOrFail();
        Assert::assertCount(1, $orderLineItemParent->children);
        Assert::assertSame($orderLineItemParent->id, $lineItemChild->parent_id);
        $childSubscription = Subscription::where('uuid', $lineItemChild->subscription_uuid)->firstOrFail();
        Assert::assertSame($parentSubscription->id, $childSubscription->parent_subscription_id);
    }

    #[Test]
    public function createSubscriptionsFromOrderWithParentOrderLines(): void
    {
        $order = new OrderFactory()->for(new CustomerFactory()->withAddress()->createOne())->createOne();

        // ssl
        ProviderFactory::new()->createOne(['type' => ProviderType::SSL, 'slug' => ProviderSlug::OPEN_PROVIDER, 'enabled' => true, 'default' => true]);
        $sslProduct = new ProductFactory()->for(new ProductGroupFactory()->ssl())->createOne();
        new ProductPriceComponentFactory()->for($sslProduct)->registration()->createOne();
        new OrderLineItemFactory()->withPrice()->for($order)->for($sslProduct)
            ->createOne([
                'subscription_uuid' => null,
                'status' => 'registration',
                'domain' => 'ssl',
            ]);
        // hosting basic
        $hostingProductGroup = new ProductGroupFactory()->hosting()->createOne();
        $hostingProduct = new ProductFactory()->for($hostingProductGroup)->createOne();
        new ProductPriceComponentFactory()->for($hostingProduct)->registration()->createOne();
        new OrderLineItemFactory()->withPrice()->for($order)->for($hostingProduct)
            ->createOne([
                'subscription_uuid' => null,
                'status' => 'registration',
                'domain' => 'hosting',
            ]);
        // domain nl
        ProviderFactory::new()->createOne(['type' => ProviderType::DOMAIN, 'slug' => ProviderSlug::REALTIME_REGISTER, 'enabled' => true, 'default' => true]);
        $domainProduct = new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne();
        new ProductPriceComponentFactory()->for($domainProduct)->registration()->createOne();
        $domainOrderLine = new OrderLineItemFactory()->withPrice()->for($order)->for($domainProduct)
            ->createOne([
                'subscription_uuid' => null,
                'status' => 'registration',
                'domain' => 'domain',
            ]);
        // free dns (child)
        $freeDnsProduct = new ProductFactory()->for($hostingProductGroup)->createOne();

        new ProductPriceComponentFactory()->for($freeDnsProduct)->registration()->createOne();

        new OrderLineItemFactory()->withPrice()->for($order)->for($freeDnsProduct)
            ->parentOrderLineItem($domainOrderLine)
            ->createOne([
                'subscription_uuid' => null,
                'status' => 'registration',
                'domain' => 'freedns',
            ]);

        // m365
        $m365Product = new ProductFactory()->for(new ProductGroupFactory()->microsoft365())->createOne();
        new ProductPriceComponentFactory()->for($m365Product)->registration()->createOne();
        new OrderLineItemFactory()->withPrice()->for($order)->for($m365Product)
            ->createOne([
                'subscription_uuid' => null,
                'status' => 'registration',
                'domain' => 'm365',
            ]);
        $this->subscriptionService->createSubscriptionsFromOrder($order);

        $order->refresh();
        foreach ($order->lineItems as $lineItem) {
            Assert::assertNotNull($lineItem->subscription_uuid);
            Assert::assertNotNull($lineItem->subscription);
        }
    }

    #[Test]
    public function createChildSubscription(): void
    {
        $domainSubscription = DomainSubscriptionDataProvider::subscription();
        $childProduct = ProductFactory::new()
            ->for($domainSubscription->product->productGroup)
            ->createOne(['slug' => 'child-product', 'name' => 'Child Product']);
        new ProductPriceComponentFactory()->for($childProduct)->registration()->createOne();

        $childSubscription = $this->subscriptionService->createChildSubscription($domainSubscription, $childProduct);

        Assert::assertSame($childProduct->id, $childSubscription->product->id);
        Assert::assertSame($domainSubscription->id, $childSubscription->parent_subscription_id);
    }

    #[Test]
    public function createFreeParentSubscription(): void
    {
        $productGroup = new ProductGroupFactory()->microsoft365()->createOne();
        $parentProduct = new ProductFactory()->for($productGroup)->createOne(['slug' => 'parent-product']);
        $childProduct = new ProductFactory()->for($productGroup)->createOne(['slug' => 'child-product']);

        $childSubscription = new SubscriptionFactory()->for(new CustomerFactory()->createOne())->for($childProduct)->createOne();

        $parentSubscription = $this->subscriptionService->createFreeParentSubscription($childSubscription, $parentProduct);

        Assert::assertSame($parentProduct->id, $parentSubscription->product->id);
        Assert::assertNotNull($parentSubscription->activePrice);
        Assert::assertSame(1, $parentSubscription->activePrice->components()->count());
        Assert::assertSame(PriceComponentType::REGISTRATION, $parentSubscription->activePrice->components()->firstOrFail()->type);
        Assert::assertSame(0, $parentSubscription->activePrice->components()->firstOrFail()->new_price);
    }

    #[Test]
    public function copyOrderLineItemPricesToSubscriptionUponCreation(): void
    {
        new ProviderFactory()->createOne(['type' => ProviderType::DOMAIN, 'slug' => ProviderSlug::REALTIME_REGISTER, 'enabled' => true, 'default' => true]);
        $order = new OrderFactory()->for(new CustomerFactory()->withAddress())->createOne();
        $product = new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne();
        $price1 = new ProductPriceComponentFactory()->for($product)->createOne(['type' => PriceComponentType::REGISTRATION]);
        $price2 = new ProductPriceComponentFactory()->for($product)->createOne(['type' => PriceComponentType::PRODUCT_GROUP]);
        $orderLine = new OrderLineItemFactory()->for($order)->for($product)->createOne();

        $orderLinePrice = new OrderLinePrice();
        $orderLinePrice->order_line_item_id = $orderLine->id;
        $orderLinePrice->valid_from = CarbonImmutable::now();
        $orderLinePrice->net_price = $price2->price;
        $orderLinePrice->save();

        $component1 = new OrderLinePriceComponent();
        $component1->order_line_price_id = $orderLinePrice->id;
        $component1->type = $price1->type;
        $component1->fixed_price = $price1->price;
        $component1->new_price = $price1->price;
        $component1->order_applied = 1;
        $component1->save();

        $component2 = new OrderLinePriceComponent();
        $component2->order_line_price_id = $orderLinePrice->id;
        $component2->type = $price2->type;
        $component2->fixed_price = $price2->price;
        $component2->new_price = $price2->price;
        $component2->order_applied = 2;
        $component2->save();

        $this->subscriptionService->createSubscriptionsFromOrder($order);

        $subscription = $orderLine->refresh()->subscription;
        self::assertInstanceOf(Subscription::class, $subscription);
        self::assertInstanceOf(SubscriptionPrice::class, $subscription->activePrice);
        self::assertCount(2, $subscription->activePrice->components);

        self::assertSame($price1->type, $component1->type);
        self::assertSame($price1->price, $component1->fixed_price);
        self::assertSame($price2->type, $component2->type);
        self::assertSame($price2->price, $component2->fixed_price);
    }
}
