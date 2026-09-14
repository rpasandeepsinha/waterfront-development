<?php

declare(strict_types=1);

namespace Tests\Domain\Products\Integration;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductIntroductionDiscountsFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\OrderController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\ProductPrice\PriceResolverHandler\IntroDiscountPricePriceHandler;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Jobs\RenewSubscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionRenewService;
use Waterfront\Infra\Common\DateTimeFormat;

#[CoversClass(OrderController::class)]
#[CoversClass(IntroDiscountPricePriceHandler::class)]
class IntroductionDiscountPriceTest extends IntegrationTestCase
{
    private Product $product;

    private Order $order;

    private Customer $customer;

    private int $introductionPrice = 100;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'enabled' => true,
            'default' => true,
        ]);

        $this->customer = new CustomerFactory()->withAddress()->createOneQuietly();

        $productGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::EXTENSION,
            'name' => ProductGroupType::EXTENSION,
        ]);

        $this->product = new ProductFactory()->createOne([
            'slug' => 'nl_domain',
            'name' => '.nl',
            'product_group_id' => $productGroup->id,
        ]);

        new ProductPriceComponentFactory()
            ->for($this->product)
            ->registration()
            ->createOne(['price' => 1000]);
        new ProductPriceComponentFactory()
            ->for($this->product)
            ->introduction()
            ->createOne(['price' => $this->introductionPrice]);
        new ProductPriceComponentFactory()
            ->for($this->product)
            ->prolongation()
            ->createOne(['price' => 1000]);

        $dnsGroup = new ProductGroupFactory()->dns()->createOne(['name' => ProductGroupType::DNS]);
        $dnsProduct = new ProductFactory()->for($dnsGroup)->createOne([
            'name' => 'free-dns',
            'slug' => 'free-dns',
        ]);
        new ProductPriceComponentFactory()
            ->for($dnsProduct)
            ->registration()
            ->createOne(['price' => 0]);
        new ProductPriceComponentFactory()
            ->for($dnsProduct)
            ->registration()
            ->createOne([
                'price' => 0,
                'billing_period' => 24,
                'contract_period' => 24,
            ]);

        $this->order = new OrderFactory()->createOne(['customer_id' => $this->customer->id]);

        // the rule that determines how many of 1 product can be ordered
        // before the introduction discount does not apply anymore
        // set the maximum amount of orderable products to 5, after the 5th the introduction discount
        // must not be applied anymore
        new ProductIntroductionDiscountsFactory()->createOne([
            'product_id' => $this->product->id,
            'max_uses_per_customer' => 5,
            'contract_period' => 12,
        ]);
    }

    /**
     * Create a product price with an introduction price discount.
     *
     *  The customer does not have ordered a product yet with an introduction price discount
     *  Test must prove that the customer can order 4 products with an introduction discount.
     *
     *  Prepare a product price for nl domain with an introduction discount price
     *  Prepare a product_price_discount with the coupled product
     *
     *  Verify that the order line items do not contain records of previous bought products of this type
     *  Verify that after the order, the order line items contain the introduction price as net_price
     *  Verify that the price resolver uses the introduction discount price
     */
    #[Test]
    public function discountRuleMaxAmountNotReached(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/data/order_payload_intro_discount_all.json');
        $this->app->bind(Dispatcher::class, fn () => self::createStub(Dispatcher::class));

        $response = $this->actingAsCustomer($this->customer)->json(
            'post',
            $this->generateRoute('partners.order.order'),
            (array) json_decode($json, true, 512, JSON_THROW_ON_ERROR),
        );

        $response->assertOk();

        /** @var array<string, array<int, OrderLineItem>> $content */
        $content = (array) json_decode((string) $response->getContent(), null, 512, JSON_THROW_ON_ERROR);
        self::assertSame($content['data'][0]->net_price, $this->introductionPrice);
    }

    /**
     * Test one left.
     *
     *  The customer does have ordered 4 in the past of this product
     *  The customer will order 2 more of this product
     *
     *  Test must prove that 1 product has an introduction discount.
     *  Test must prove that 1 product has the regular price.
     */
    #[Test]
    public function discountRuleOneLeft(): void
    {
        new OrderLineItemFactory()
            ->count(4)
            ->createQuietly([
                'subscription_uuid' => null,
                'order_id' => $this->order->id,
                'domain' => 'test-domein.nl',
                'product_uuid' => $this->product->uuid,
            ]);

        $json = (string) file_get_contents(__DIR__ . '/data/order_payload_intro_discount_one.json');
        $this->app->bind(Dispatcher::class, fn () => self::createStub(Dispatcher::class));

        $response = $this->actingAsCustomer($this->customer)->json(
            'post',
            $this->generateRoute('partners.order.order'),
            (array) json_decode($json, true, 512, JSON_THROW_ON_ERROR),
        );

        $response->assertOk();

        /** @var array<string, array<int, OrderLineItem>> $content */
        $content = (array) json_decode((string) $response->getContent(), null, 512, JSON_THROW_ON_ERROR);
        self::assertSame($content['data'][0]->net_price, $this->introductionPrice);
    }

    /**
     * Test with a customer that has no order history of the ordered product
     *  - An introduct discount rule exists with nl_domain for 12 months with a max of 5
     *  - Customer places an order with 6 domains.
     *
     * Test must prove that customer can order 5 domains with introduction discount
     * Test must prove that customer will pay regular price on 6th ordered domain
     */
    #[Test]
    public function discountRuleTotalPriceNoOrderHistory(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/data/order_payload_intro_total_no_history.json');
        $this->app->bind(Dispatcher::class, fn () => self::createStub(Dispatcher::class));

        $response = $this->actingAsCustomer($this->customer)->json(
            'post',
            $this->generateRoute('partners.order.order'),
            (array) json_decode($json, true, 512, JSON_THROW_ON_ERROR),
        );

        $response->assertOk();
    }

    /**
     * Test must prove that intro discount price only applies on specific period
     *  Customer orders a domain with period of 24 monts
     *  Intro discount price is only applied on period 12 months.
     *
     *  - prove that customer gets the regular price instead of discount price
     */
    #[Test]
    public function discountRuleTotalPriceInvalidPeriod(): void
    {
        new ProductPriceComponentFactory()
            ->for($this->product)
            ->registration()
            ->createOne(['price' => 1000, 'contract_period' => 24, 'billing_period' => 24]);
        new ProductPriceComponentFactory()
            ->for($this->product)
            ->introduction()
            ->createOne(['price' => $this->introductionPrice, 'contract_period' => 24, 'billing_period' => 24]);

        $json = (string) file_get_contents(__DIR__ . '/data/order_payload_intro_total_invalid_period.json');
        $this->app->bind(Dispatcher::class, fn () => self::createStub(Dispatcher::class));

        $response = $this->actingAsCustomer($this->customer)->json(
            'post',
            $this->generateRoute('partners.order.order'),
            (array) json_decode($json, true, 512, JSON_THROW_ON_ERROR),
        );

        $response->assertOk();
    }

    /**
     * Test must prove that any attempt to "hack" a request, the system will throw an exception
     *  1337 hax0r tempered the request to make the product period 24 months instead of 12 months
     *  This does not exist.
     *
     *  - prove that system will break on this and catches it returning an invalid total price
     */
    #[Test]
    public function discountRuleTotalPriceInvalidProductPrice(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/data/order_payload_intro_total_invalid_period.json');

        $response = $this->actingAsCustomer($this->customer)->json(
            'post',
            $this->generateRoute('partners.order.order'),
            (array) json_decode($json, true, 512, JSON_THROW_ON_ERROR),
        );

        $response->assertUnprocessable();
    }

    /**
     * Test must prove that a subscription that once is bought with an introduction discount
     *  will not receive again a discount on renewal. The renewal price must be the regular price.
     *
     * An introduction discount is stored in the net_price property in the subscription object. Subscriptions
     *  knows nothing about a regular price or introduction price. Whenever the renewal process is going to run
     *  it will ask the price from the PriceResolver. There lies the responsibility of the correct price and thus
     *  the PriceResolver must give back the regular price on renewal instead of the introduction discount price
     *
     * In this context it should not matter if the customer still has 4 discounts left or 0
     */
    #[Test]
    public function prolongationIntroductionDiscountPrice(): void
    {
        // create existing subscription with the introduction price
        $previousDate = new CarbonImmutable('1 year ago');
        $nextBillingDate = new CarbonImmutable();

        $subscription = new SubscriptionFactory()->for($this->customer)->createOneQuietly(
            [
                'product_uuid' => $this->product->uuid,
                'net_price' => 100,
                'created_at' => $previousDate->format(DateTimeFormat::DEFAULT),
                'end_date' => $nextBillingDate->format(DateTimeFormat::DEFAULT),
                'billing_period' => 12,
                'contract_period' => 12,
                'next_billing_date' => $nextBillingDate->addYear()->format(DateTimeFormat::DEFAULT),
            ],
        );

        // start renewal process
        $subscriptionRenewService = self::resolve(SubscriptionRenewService::class);
        new RenewSubscription($subscription)->handle($subscriptionRenewService, self::resolve(Dispatcher::class));

        self::assertSame(1000, $subscription->net_price);
    }

    #[Test]
    public function prolongationIntroductionDiscountPriceNoAvailability(): void
    {
        new OrderLineItemFactory()
            ->count(5)
            ->createQuietly([
                'subscription_uuid' => null,
                'order_id' => $this->order->id,
                'domain' => 'test-domein.nl',
                'product_uuid' => $this->product->uuid,
            ]);

        // create existing subscription with the introduction price
        $previousDate = new CarbonImmutable('1 year ago');
        $nextBillingDate = new CarbonImmutable();

        $subscription = new SubscriptionFactory()->for($this->customer)->createOneQuietly(
            [
                'product_uuid' => $this->product->uuid,
                'net_price' => 100,
                'created_at' => $previousDate->modify('-1 year')->format(DateTimeFormat::DEFAULT),
                'end_date' => $nextBillingDate->format(DateTimeFormat::DEFAULT),
                'billing_period' => 12,
                'contract_period' => 12,
                'next_billing_date' => $nextBillingDate->addYear()->format(DateTimeFormat::DEFAULT),
            ],
        );

        // start renewal process
        $subscriptionRenewService = self::resolve(SubscriptionRenewService::class);
        new RenewSubscription($subscription)->handle($subscriptionRenewService, self::resolve(Dispatcher::class));

        self::assertSame(1000, $subscription->net_price);
    }

    #[Test]
    public function discountRuleExpiredIntroductionPriceComponentUsesRegularPrice(): void
    {
        ProductPriceComponent::query()
            ->where('product_id', $this->product->id)
            ->where('type', PriceComponentType::INTRODUCTION)
            ->update(['expires_at' => CarbonImmutable::yesterday()]);

        $json = (string) file_get_contents(__DIR__ . '/data/order_payload_intro_discount_all.json');
        $this->app->bind(Dispatcher::class, fn () => self::createStub(Dispatcher::class));

        $response = $this->actingAsCustomer($this->customer)->json(
            'post',
            $this->generateRoute('partners.order.order'),
            (array) json_decode($json, true, 512, JSON_THROW_ON_ERROR),
        );

        $response->assertOk();

        /** @var array<string, array<int, OrderLineItem>> $content */
        $content = (array) json_decode((string) $response->getContent(), null, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1000, $content['data'][0]->net_price);
    }

    #[Test]
    public function discountRuleFutureIntroductionPriceComponentUsesRegularPrice(): void
    {
        ProductPriceComponent::query()
            ->where('product_id', $this->product->id)
            ->where('type', PriceComponentType::INTRODUCTION)
            ->update(['expires_at' => CarbonImmutable::yesterday()]);

        new ProductPriceComponentFactory()
            ->for($this->product)
            ->introduction()
            ->createOne([
                'price' => 777,
                'starts_at' => CarbonImmutable::tomorrow(),
            ]);

        $json = (string) file_get_contents(__DIR__ . '/data/order_payload_intro_discount_all.json');
        $this->app->bind(Dispatcher::class, fn () => self::createStub(Dispatcher::class));

        $response = $this->actingAsCustomer($this->customer)->json(
            'post',
            $this->generateRoute('partners.order.order'),
            (array) json_decode($json, true, 512, JSON_THROW_ON_ERROR),
        );

        $response->assertOk();

        /** @var array<string, array<int, OrderLineItem>> $content */
        $content = (array) json_decode((string) $response->getContent(), null, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1000, $content['data'][0]->net_price);
    }

    #[Test]
    public function discountRuleMostRecentlyStartedIntroductionPriceComponentIsUsed(): void
    {
        new ProductPriceComponentFactory()
            ->for($this->product)
            ->introduction()
            ->createOne([
                'price' => 9999,
                'starts_at' => CarbonImmutable::now()->subDays(2),
            ]);

        $json = (string) file_get_contents(__DIR__ . '/data/order_payload_intro_discount_all.json');
        $this->app->bind(Dispatcher::class, fn () => self::createStub(Dispatcher::class));

        $response = $this->actingAsCustomer($this->customer)->json(
            'post',
            $this->generateRoute('partners.order.order'),
            (array) json_decode($json, true, 512, JSON_THROW_ON_ERROR),
        );

        $response->assertOk();

        /** @var array<string, array<int, OrderLineItem>> $content */
        $content = (array) json_decode((string) $response->getContent(), null, 512, JSON_THROW_ON_ERROR);
        self::assertSame($this->introductionPrice, $content['data'][0]->net_price);
    }
}
