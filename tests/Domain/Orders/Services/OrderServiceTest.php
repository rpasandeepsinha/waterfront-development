<?php

declare(strict_types=1);

namespace Tests\Domain\Orders\Services;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Tests\DataProvider\DomainSubscriptionDataProvider;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\ProductAllowedChangeFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\VoucherFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Cart\DTO\AppliedPrice;
use Waterfront\Domain\Cart\DTO\CartVoucher;
use Waterfront\Domain\Cart\DTO\RegularPrice;
use Waterfront\Domain\Cart\Services\CartService;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Experiment\Enums\ExperimentType;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Orders\DTO\CartOrder;
use Waterfront\Domain\Orders\Enums\OrderLineItemStatus;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Orders\LineItemCreators\LineItemCreator;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Orders\Repositories\OrderRepository;
use Waterfront\Domain\Orders\Serializers\CartSerializerFactory;
use Waterfront\Domain\Orders\Services\OrderService;
use Waterfront\Domain\Pricing\DTO\PriceComponents\CustomIndefinitePriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\RegistrationPriceComponent;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Repositories\PriceRepository;
use Waterfront\Domain\Pricing\Services\PricePersistService;
use Waterfront\Domain\Products\CalculatePriceService;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\ProductWithCalculatedPrice;
use Waterfront\Domain\Products\DTO\TotalCollectionPrice;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Enums\UsedProductPriceType;
use Waterfront\Domain\Subscriptions\Actions\ExtendContractAction;
use Waterfront\Domain\Subscriptions\Jobs\ChangeProvisioningJob;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\ProductAllowedChangeRepository;
use Waterfront\Domain\Subscriptions\Services\SubscriptionChangeService;
use Waterfront\Domain\Voucher\Enum\VoucherAmountType;
use Waterfront\Domain\Voucher\Models\VoucherClaim;
use Waterfront\Domain\Voucher\Services\VoucherService;

#[CoversClass(OrderService::class)]
class OrderServiceTest extends IntegrationTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function processCartToOrder(): void
    {
        $vpsGroup = new ProductGroupFactory()->vps()->createOne();
        $product = new ProductFactory()->for($vpsGroup)->createOne(['slug' => 'vps-32-red']);
        new ProductPriceComponentFactory()
            ->for($product)
            ->registration()
            ->state([
                'contract_period' => 12,
                'billing_period' => 12,
                'price' => 10,
            ]);

        $product = new ProductFactory()->for($vpsGroup)->createOne(['slug' => 'ubuntu-lts-20.04']);
        new ProductPriceComponentFactory()
            ->for($product)
            ->registration()
            ->state([
                'contract_period' => 1,
                'billing_period' => 1,
                'price' => 10,
            ]);

        $serializer = new CartSerializerFactory()->get();
        $cartJson = (string) file_get_contents(__DIR__ . '/data/vps-child-cart.json');
        $cartOrder = $serializer->deserialize($cartJson, CartOrder::class, 'json');
        $service = $this->resolve(OrderService::class);
        $cartItem1 = new ProductWithCalculatedPrice(
            uuid: Uuid::fromString('ad673cf3-f128-456a-8c01-498d48ba12f8'),
            parentItemUuid: null,
            slug: 'slug',
            productId: 1,
            billingPeriod: 1,
            contractPeriod: 1,
            regularPrice: new RegularPrice(priceInclVat: 0, priceExclVat: 0),
            appliedPrice: new AppliedPrice(
                priceInclVat: 0,
                priceExclVat: 0,
                priceType: UsedProductPriceType::REGULAR_PRICE,
                actionPeriod: null,
                actionPeriodPrice: null,
                voucher: null,
                priceExplanation: null,
            ),
            price: new Price(
                ProductPriceType::REGISTRATION,
                12,
                1,
                'uuid',
                0,
                12,
                true,
                true,
                appliedPriceComponents: [new RegistrationPriceComponent(10)],
                calculatedPrice: 0,
            ),
        );
        $cartItem2 = new ProductWithCalculatedPrice(
            uuid: Uuid::fromString('fda0da0b-4678-4716-ae8b-b30c62139137'),
            parentItemUuid: null,
            slug: 'slug',
            productId: 1,
            billingPeriod: 1,
            contractPeriod: 1,
            regularPrice: new RegularPrice(priceInclVat: 0, priceExclVat: 0),
            appliedPrice: new AppliedPrice(
                priceInclVat: 0,
                priceExclVat: 0,
                priceType: UsedProductPriceType::REGULAR_PRICE,
                actionPeriod: null,
                actionPeriodPrice: null,
                voucher: null,
                priceExplanation: null,
            ),
            price: new Price(
                ProductPriceType::REGISTRATION,
                12,
                1,
                'uuid',
                0,
                12,
                true,
                true,
                appliedPriceComponents: [new RegistrationPriceComponent(10)],
                calculatedPrice: 0,
            ),
        );

        $order = $service->processCartToOrder(
            $cartOrder,
            new TotalCollectionPrice(new Collection([$cartItem1, $cartItem2]), [], 0, 0),
            5,
            $this->customer,
        );

        self::assertModelExists($order);
    }

    #[Test]
    public function redirectProcessCartToOrder(): void
    {
        $product = new ProductFactory()->redirect()->createOne();

        new ProductPriceComponentFactory()
            ->for($product)
            ->registration()
            ->state([
                'contract_period' => 12,
                'billing_period' => 12,
                'price' => 10,
            ]);

        $serializer = new CartSerializerFactory()->get();
        $cartJson = (string) file_get_contents(__DIR__ . '/data/cart-with-redirect.json');
        $cartOrder = $serializer->deserialize($cartJson, CartOrder::class, 'json');
        $service = $this->resolve(OrderService::class);
        $cartItem = new ProductWithCalculatedPrice(
            uuid: Uuid::fromString('ad673cf3-f128-456a-8c01-498d48ba12f8'),
            parentItemUuid: null,
            slug: 'slug',
            productId: 1,
            billingPeriod: 1,
            contractPeriod: 1,
            regularPrice: new RegularPrice(priceInclVat: 0, priceExclVat: 0),
            appliedPrice: new AppliedPrice(
                priceInclVat: 0,
                priceExclVat: 0,
                priceType: UsedProductPriceType::REGULAR_PRICE,
                actionPeriod: null,
                actionPeriodPrice: null,
                voucher: null,
                priceExplanation: null,
            ),
            price: new Price(
                ProductPriceType::REGISTRATION,
                12,
                1,
                'uuid',
                0,
                12,
                true,
                true,
                appliedPriceComponents: [new RegistrationPriceComponent(10)],
                calculatedPrice: 0,
            ),
        );

        $order = $service->processCartToOrder(
            $cartOrder,
            new TotalCollectionPrice(new Collection([$cartItem]), [], 0, 0),
            5,
            $this->customer,
        );

        self::assertModelExists($order);
    }

    #[Test]
    public function redirectShouldHaveDomainInProcessCartToOrder(): void
    {
        self::expectException(MissingConstructorArgumentsException::class);
        self::expectExceptionMessageIs(
            'Cannot create an instance of "Waterfront\Domain\Orders\DTO\CartOrderLines\RedirectLineItem" from serialized data because its constructor requires the following parameters to be present : "$domain".',
        );

        new ProductFactory()->redirect()->createOne();

        $serializer = new CartSerializerFactory()->get();
        $cartJson = (string) file_get_contents(__DIR__ . '/data/cart-with-redirect-no-domain.json');
        $cartOrder = $serializer->deserialize($cartJson, CartOrder::class, 'json');
        $service = $this->resolve(OrderService::class);
        $service->processCartToOrder(
            $cartOrder,
            new TotalCollectionPrice(new Collection(), [], 0, 0),
            5,
            $this->customer,
        );
    }

    #[Test]
    public function noVouchers(): void
    {
        $serializer = new CartSerializerFactory()->get();
        $cartJson = (string) file_get_contents(__DIR__ . '/data/cart.json');
        $cartOrder = $serializer->deserialize($cartJson, CartOrder::class, 'json');

        $service = $this->resolve(OrderService::class);

        $service->processCartToOrder(
            $cartOrder,
            new TotalCollectionPrice(new Collection(), [], 0, 0),
            5,
            $this->customer,
        );

        self::assertDatabaseEmpty(VoucherClaim::class);
    }

    #[Test]
    public function voucherPersistence(): void
    {
        $voucher = new VoucherFactory()->createOne();

        $productGroup = new ProductGroupFactory()->vps()->createOne();

        $product = new ProductFactory()->for($productGroup)->createOne(['slug' => ProductGroupType::VPS]);

        new ProductPriceComponentFactory()
            ->for($product)
            ->registration()
            ->state([
                'contract_period' => 12,
                'billing_period' => 12,
                'price' => 10,
            ]);

        $product = new ProductFactory()->for($productGroup)->createOne(['slug' => 'vps-32-red']);
        new ProductPriceComponentFactory()
            ->for($product)
            ->registration()
            ->state([
                'contract_period' => 12,
                'billing_period' => 12,
                'price' => 10,
            ]);

        $serializer = new CartSerializerFactory()->get();
        $cartJson = (string) file_get_contents(__DIR__ . '/data/cart-with-voucher.json');
        $cartOrder = $serializer->deserialize($cartJson, CartOrder::class, 'json');

        $cartVoucher = new CartVoucher($voucher->id, '', '', '', 0, VoucherAmountType::FIXED, true, 0);
        $productWithCalculatedPrice = new ProductWithCalculatedPrice(
            Uuid::fromString('82ababbd-45a5-4f03-88f1-4c97f91bf742'),
            null,
            'unimportant',
            1,
            12,
            12,
            new RegularPrice(0, 0),
            new AppliedPrice(0, 0, UsedProductPriceType::VOUCHER_PRICE, null, null, $cartVoucher, null),
            new Price(
                ProductPriceType::REGISTRATION,
                12,
                1,
                'uuid',
                0,
                12,
                true,
                true,
                appliedPriceComponents: [new RegistrationPriceComponent(10)],
                calculatedPrice: 0,
            ),
        );
        $totalPriceCollection = new TotalCollectionPrice(new Collection([$productWithCalculatedPrice]), [], 0, 0);

        $service = $this->resolve(OrderService::class);
        $service->processCartToOrder($cartOrder, $totalPriceCollection, 5, $this->customer);

        $orderLineItem = OrderLineItem::firstOrFail();
        self::assertDatabaseHas(
            VoucherClaim::class,
            [
                'order_line_item_id' => $orderLineItem->id,
                'voucher_id' => $voucher->id,
            ],
        );
    }

    #[Test]
    public function UnprocessedOrderWillBeMarkedAsAbuse(): void
    {
        $order = new OrderFactory()->for($this->customer)->createOne(['status' => OrderStatus::IN_PROGRESS]);
        new OrderLineItemFactory()->for($order)->createOne();
        $order2 = new OrderFactory()->for($this->customer)->createOne(['status' => OrderStatus::ON_HOLD]);
        new OrderLineItemFactory()->for($order2)->createOne();

        $service = new OrderService(
            lineItemCreator: self::createStub(LineItemCreator::class),
            orderRepository: self::resolve(OrderRepository::class),
            voucherService: self::resolve(VoucherService::class),
            subscriptionChangeService: self::createStub(SubscriptionChangeService::class),
            extendContractAction: self::createStub(ExtendContractAction::class),
            productAllowedChangeRepository: self::resolve(ProductAllowedChangeRepository::class),
            pricePersistService: self::resolve(PricePersistService::class),
            jobDispatcher: self::resolve(Dispatcher::class),
        );
        self::assertSame(OrderStatus::IN_PROGRESS, $order->status);
        self::assertSame(OrderStatus::ON_HOLD, $order2->status);
        $service->markNonProcessedAsAbuseForCustomer($this->customer);
        $order->refresh();
        $order2->refresh();

        self::assertSame(OrderStatus::ABUSE, $order->status);
        self::assertSame(OrderStatus::ABUSE, $order2->status);
    }

    #[Test]
    public function ProcessedOrderWillBeNotBeMarkedAsAbuse(): void
    {
        $order = new OrderFactory()->for($this->customer)->createOne(['status' => OrderStatus::PROCESSED]);
        new OrderLineItemFactory()->for($order)->createOne();

        $service = new OrderService(
            lineItemCreator: self::createStub(LineItemCreator::class),
            orderRepository: self::resolve(OrderRepository::class),
            voucherService: self::resolve(VoucherService::class),
            subscriptionChangeService: self::createStub(SubscriptionChangeService::class),
            extendContractAction: self::createStub(ExtendContractAction::class),
            productAllowedChangeRepository: self::resolve(ProductAllowedChangeRepository::class),
            pricePersistService: self::resolve(PricePersistService::class),
            jobDispatcher: self::resolve(Dispatcher::class),
        );
        self::assertSame(OrderStatus::PROCESSED, $order->status);
        $service->markNonProcessedAsAbuseForCustomer($this->customer);
        $order->refresh();

        self::assertSame(OrderStatus::PROCESSED, $order->status);
    }

    #[Test]
    public function processCartToOrderWithMultiYearPricing(): void
    {
        $extensionGroup = new ProductGroupFactory()->extension()->createOne();
        $product = new ProductFactory()->for($extensionGroup)->createOne(['slug' => 'extension_com']);
        new ProductPriceComponentFactory()
            ->registration()
            ->for($product)
            ->createOne([
                'contract_period' => 12,
                'billing_period' => 12,
                'price' => 800,
            ]);
        new ProductPriceComponentFactory()->for($product)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'contract_period' => 12,
            'billing_period' => 12,
            'price' => 700,
        ]);
        new ProductPriceComponentFactory()
            ->registration()
            ->for($product)
            ->createOne([
                'contract_period' => 36,
                'billing_period' => 36,
                'price' => 500,
            ]);
        new ProductPriceComponentFactory()->for($product)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'contract_period' => 36,
            'billing_period' => 36,
            'price' => 400,
        ]);

        $serializer = new CartSerializerFactory()->get();
        $cartJson = (string) file_get_contents(__DIR__ . '/data/cart-with-multi-year-domain.json');
        $cartOrder = $serializer->deserialize($cartJson, CartOrder::class, 'json');

        $cartService = $this->resolve(CartService::class);
        $calculatePriceService = $this->resolve(CalculatePriceService::class);
        $orderService = $this->resolve(OrderService::class);

        $cartWithoutPrices = $cartService->convertCartOrderToProductsWithPeriodsAndPrice($cartOrder);

        $validVouchers = $cartService->getValidVoucherCodes($cartOrder->vouchers ?? [], $this->customer);
        $vouchers = $cartService->getVouchers($validVouchers);

        $totalPriceDto = $calculatePriceService->calculatePrices($this->customer, $cartWithoutPrices, $vouchers);

        $order = $orderService->processCartToOrder($cartOrder, $totalPriceDto, 5, $this->customer);
        self::assertCount(1, $order->lineItems);

        $orderLine = $order->lineItems->firstOrFail();
        self::assertSame(500, $orderLine->gross_price);
        self::assertSame(400, $orderLine->net_price);
        self::assertSame(36, $orderLine->contract_period);
        self::assertSame(36, $orderLine->billing_period);
    }

    #[Test]
    public function processCartToOrderWithExperimentType(): void
    {
        $extensionGroup = new ProductGroupFactory()->extension()->createOne();
        $product = new ProductFactory()->for($extensionGroup)->createOne(['slug' => 'extension_com']);
        new ProductPriceComponentFactory()
            ->registration()
            ->for($product)
            ->createOne([
                'contract_period' => 12,
                'billing_period' => 12,
                'price' => 800,
            ]);
        new ProductPriceComponentFactory()->for($product)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'contract_period' => 12,
            'billing_period' => 12,
            'price' => 700,
        ]);
        new ProductPriceComponentFactory()
            ->registration()
            ->for($product)
            ->createOne([
                'contract_period' => 36,
                'billing_period' => 36,
                'price' => 500,
            ]);
        new ProductPriceComponentFactory()->for($product)->createOne([
            'type' => PriceComponentType::PROMOTION,
            'contract_period' => 36,
            'billing_period' => 36,
            'price' => 400,
        ]);

        $serializer = new CartSerializerFactory()->get();
        $cartJson = (string) file_get_contents(__DIR__ . '/data/cart-with-experiment-type.json');
        $cartOrder = $serializer->deserialize($cartJson, CartOrder::class, 'json');

        $cartService = $this->resolve(CartService::class);
        $calculatePriceService = $this->resolve(CalculatePriceService::class);
        $orderService = $this->resolve(OrderService::class);

        $cartWithoutPrices = $cartService->convertCartOrderToProductsWithPeriodsAndPrice($cartOrder);

        $validVouchers = $cartService->getValidVoucherCodes($cartOrder->vouchers ?? [], $this->customer);
        $vouchers = $cartService->getVouchers($validVouchers);

        $totalPriceDto = $calculatePriceService->calculatePrices($this->customer, $cartWithoutPrices, $vouchers);

        $order = $orderService->processCartToOrder($cartOrder, $totalPriceDto, 5, $this->customer);
        self::assertCount(1, $order->lineItems);

        $orderLine = $order->lineItems->firstOrFail();
        self::assertSame(500, $orderLine->gross_price);
        self::assertSame(400, $orderLine->net_price);
        self::assertSame(36, $orderLine->contract_period);
        self::assertSame(36, $orderLine->billing_period);
        self::assertSame(ExperimentType::PRICING_LADDER->value, $orderLine->experiment_slug);
    }

    #[Test]
    public function orderIsUpgradeOrder(): void
    {
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $basicProduct = new ProductFactory()->for($productGroup)->createOne(['slug' => 'basic']);
        $grootProduct = new ProductFactory()->for($productGroup)->createOne(['slug' => 'groot']);

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($basicProduct)
            ->createOne();

        $order = new OrderFactory()->createOne([
            'customer_id' => $this->customer->id,
        ]);

        new OrderLineItemFactory()->createOne([
            'order_id' => $order->id,
            'status' => OrderLineItemStatus::PROLONGATION,
            'product_uuid' => $grootProduct->uuid,
            'product_name' => $grootProduct->name,
            'subscription_uuid' => $subscription->uuid,
        ]);

        $orderService = self::resolve(OrderService::class);

        $result = $orderService->orderContainsOnlyUpgradesOrAddonsOrMutations($order);
        self::assertTrue($result);
    }

    #[Test]
    public function orderIsUpgradeOrderAndContainsAddon(): void
    {
        $addonGroup = new ProductGroupFactory()->addon()->createOne();
        $addonProduct = new ProductFactory()->for($addonGroup)->createOne(['slug' => 'trustee']);

        $hostingGroup = new ProductGroupFactory()->hosting()->createOne();
        $basicProduct = new ProductFactory()->for($hostingGroup)->createOne(['slug' => 'basic']);
        $grootProduct = new ProductFactory()->for($hostingGroup)->createOne(['slug' => 'groot']);

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($basicProduct)
            ->createOne();

        $order = new OrderFactory()->createOne([
            'customer_id' => $this->customer->id,
        ]);

        $parent = new OrderLineItemFactory()->createOne([
            'order_id' => $order->id,
            'status' => OrderLineItemStatus::PROLONGATION,
            'product_uuid' => $grootProduct->uuid,
            'product_name' => $grootProduct->name,
            'subscription_uuid' => $subscription->uuid,
        ]);

        new OrderLineItemFactory()
            ->parentOrderLineItem($parent)
            ->createOne([
                'order_id' => $order->id,
                'status' => OrderLineItemStatus::REGISTRATION,
                'product_uuid' => $addonProduct->uuid,
                'product_name' => $addonProduct->name,
            ]);

        $orderService = self::resolve(OrderService::class);

        $result = $orderService->orderContainsOnlyUpgradesOrAddonsOrMutations($order);
        self::assertTrue($result);
    }

    #[Test]
    public function processMutationOrderForUpgradeAndAddon(): void
    {
        $addonGroup = new ProductGroupFactory()->addon()->createOne();
        $addonProduct = new ProductFactory()->for($addonGroup)->createOne(['slug' => 'trustee']);

        $hostingGroup = new ProductGroupFactory()->hosting()->createOne();
        $basicProduct = new ProductFactory()->for($hostingGroup)->createOne(['slug' => 'basic']);
        $grootProduct = new ProductFactory()->for($hostingGroup)->createOne(['slug' => 'groot']);

        new ProductPriceComponentFactory()
            ->prolongation()
            ->for($grootProduct)
            ->createOne();

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($basicProduct)
            ->createOne();

        new ProductAllowedChangeFactory()
            ->upgradeChange()
            ->createOne([
                'from_product_id' => $basicProduct->id,
                'to_product_id' => $grootProduct->id,
            ]);

        $order = new OrderFactory()->createOne([
            'customer_id' => $this->customer->id,
        ]);

        $parent = new OrderLineItemFactory()->createOne([
            'order_id' => $order->id,
            'status' => OrderLineItemStatus::PROLONGATION,
            'product_uuid' => $grootProduct->uuid,
            'product_name' => $grootProduct->name,
            'subscription_uuid' => $subscription->uuid,
        ]);

        new OrderLineItemFactory()
            ->parentOrderLineItem($parent)
            ->createOne([
                'order_id' => $order->id,
                'status' => OrderLineItemStatus::REGISTRATION,
                'product_uuid' => $addonProduct->uuid,
                'product_name' => $addonProduct->name,
                'subscription_uuid' => null,
            ]);

        $jobDispatcher = self::createMock(Dispatcher::class);
        $jobDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(ChangeProvisioningJob::class));

        $orderService = new OrderService(
            self::createStub(LineItemCreator::class),
            self::createStub(OrderRepository::class),
            self::createStub(VoucherService::class),
            self::resolve(SubscriptionChangeService::class),
            self::createStub(ExtendContractAction::class),
            self::resolve(ProductAllowedChangeRepository::class),
            self::createStub(PricePersistService::class),
            $jobDispatcher,
        );

        $orderService->processMutations($order);

        $addonLine = OrderLineItem::where('product_uuid', $addonProduct->uuid)->first();
        self::assertNotNull($addonLine);
        self::assertNull($addonLine->processed_at);

        $grootOrderline = OrderLineItem::where('product_uuid', $grootProduct->uuid)->first();
        self::assertNotNull($grootOrderline);
        self::assertNotNull($grootOrderline->processed_at);
        self::assertNotNull($grootOrderline->subscription_uuid);

        $mutation = $grootOrderline->subscriptionMutation;
        self::assertNotNull($mutation);
        self::assertTrue($mutation->subscription->is($subscription));
        self::assertTrue($mutation->product->is($grootProduct));
        self::assertSame($mutation->contract_period, $grootOrderline->contract_period);
        self::assertSame($mutation->billing_period, $grootOrderline->billing_period);

        $invoices = Invoice::all();
        self::assertCount(1, $invoices);
        $upgradeInvoice = $invoices->firstOrFail();
        self::assertSame($subscription->id, $upgradeInvoice->subscription_id);
        self::assertSame($grootProduct->id, $upgradeInvoice->product_id);
    }

    #[Test]
    public function processMutationOrderForPeriodChange(): void
    {
        $domainSubscription = DomainSubscriptionDataProvider::subscription();
        $order = new OrderFactory()->for(new CustomerFactory()->createOne())->createOne();

        new ProductPriceComponentFactory()
            ->for($domainSubscription->product)
            ->prolongation()
            ->createOne([
                'billing_period' => 24,
                'contract_period' => 24,
            ]);

        $lineItem = new OrderLineItemFactory()
            ->for($order)
            ->for($domainSubscription->product)
            ->createOne([
                'status' => OrderLineItemStatus::PROLONGATION->value,
                'domain' => $domainSubscription->domain,
                'subscription_uuid' => $domainSubscription->uuid,
                'billing_period' => 24,
                'contract_period' => 24,
                'processed_at' => null,
            ]);

        $dataSub = Subscription::where('id', $domainSubscription->id)->first();
        self::assertInstanceOf(Subscription::class, $dataSub);
        $dataSub->loadMissing('product');

        $orderService = new OrderService(
            self::createStub(LineItemCreator::class),
            self::createStub(OrderRepository::class),
            self::createStub(VoucherService::class),
            self::resolve(SubscriptionChangeService::class),
            self::resolve(ExtendContractAction::class),
            self::resolve(ProductAllowedChangeRepository::class),
            self::createStub(PricePersistService::class),
            self::resolve(Dispatcher::class),
        );

        $orderService->processMutations($order);

        $mutationLine = OrderLineItem::where('subscription_uuid', $domainSubscription->uuid)->first();
        self::assertNotNull($mutationLine);
        self::assertNotNull($mutationLine->processed_at);

        $mutation = $mutationLine->subscriptionMutation;
        self::assertNotNull($mutation);
        self::assertTrue($mutation->subscription->is($domainSubscription));
        self::assertTrue($mutation->product->is($domainSubscription->product));
        self::assertSame($mutation->contract_period, $lineItem->contract_period);
        self::assertSame($mutation->billing_period, $lineItem->billing_period);
    }

    #[Test]
    public function processMutationOrderForDowngrade(): void
    {
        $hostingGroup = new ProductGroupFactory()->hosting()->createOne();
        $basicProduct = new ProductFactory()->for($hostingGroup)->createOne(['slug' => 'basic']);
        $grootProduct = new ProductFactory()->for($hostingGroup)->createOne(['slug' => 'groot']);

        $hostingSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($grootProduct)
            ->createOne();

        new ProductAllowedChangeFactory()
            ->downgradeChange()
            ->createOne([
                'from_product_id' => $grootProduct->id,
                'to_product_id' => $basicProduct->id,
            ]);

        new ProductPriceComponentFactory()
            ->for($basicProduct)
            ->prolongation()
            ->createOne();

        $order = new OrderFactory()->createOne([
            'customer_id' => $this->customer->id,
        ]);

        new OrderLineItemFactory()->createOne([
            'order_id' => $order->id,
            'status' => OrderLineItemStatus::PROLONGATION,
            'product_uuid' => $basicProduct->uuid,
            'product_name' => $basicProduct->name,
            'subscription_uuid' => $hostingSubscription->uuid,
        ]);

        $orderService = new OrderService(
            self::createStub(LineItemCreator::class),
            self::createStub(OrderRepository::class),
            self::createStub(VoucherService::class),
            self::resolve(SubscriptionChangeService::class),
            self::resolve(ExtendContractAction::class),
            self::resolve(ProductAllowedChangeRepository::class),
            self::createStub(PricePersistService::class),
            self::resolve(Dispatcher::class),
        );

        $orderService->processMutations($order);

        $mutationLine = OrderLineItem::where('subscription_uuid', $hostingSubscription->uuid)->firstOrFail();
        self::assertNotNull($mutationLine->subscription_mutation_id);
    }

    #[Test]
    public function anUpgradeLineWithACustomIndefinitePriceIsNotInvoicedAndKeepsThatPrice(): void
    {
        Bus::fake([ChangeProvisioningJob::class]);

        $customer = new CustomerFactory()->withAddress()->createOne();

        $hostingGroup = new ProductGroupFactory()->hosting()->createOne();
        $basicProduct = new ProductFactory()->for($hostingGroup)->createOne(['slug' => 'basic-custom-price']);
        $grootProduct = new ProductFactory()->for($hostingGroup)->createOne(['slug' => 'groot-custom-price']);

        new ProductPriceComponentFactory()
            ->prolongation()
            ->for($grootProduct)
            ->createOne(['price' => 1000]);

        $subscription = new SubscriptionFactory()
            ->for($customer)
            ->for($basicProduct)
            ->createOne();

        new ProductAllowedChangeFactory()
            ->upgradeChange()
            ->createOne([
                'from_product_id' => $basicProduct->id,
                'to_product_id' => $grootProduct->id,
            ]);

        $order = new OrderFactory()->createOne(['customer_id' => $customer->id]);

        $orderLine = new OrderLineItemFactory()->createOne([
            'order_id' => $order->id,
            'status' => OrderLineItemStatus::REGISTRATION,
            'product_uuid' => $grootProduct->uuid,
            'product_name' => $grootProduct->name,
            'subscription_uuid' => $subscription->uuid,
            'should_invoice' => false,
        ]);

        self::resolve(PricePersistService::class)
            ->persistOrderLineItemPrice(
                $orderLine,
                new Price(
                    type: ProductPriceType::REGISTRATION,
                    billingPeriod: $orderLine->billing_period,
                    productId: $grootProduct->id,
                    productGroupUuid: $hostingGroup->uuid,
                    regularPrice: 0,
                    contractPeriod: $orderLine->contract_period,
                    orderable: true,
                    is_default: true,
                    appliedPriceComponents: [new CustomIndefinitePriceComponent(0)],
                    calculatedPrice: 0,
                ),
            );

        self::resolve(OrderService::class)->processMutations($order);

        $subscription->refresh();

        self::assertSame($grootProduct->uuid, $subscription->product_uuid);
        self::assertSame(0, $subscription->net_price);
        self::assertTrue(self::resolve(PriceRepository::class)->hasIndefiniteCustomPrice($subscription));
        self::assertSame(0, Invoice::query()->where('customer_id', $customer->id)->count());

        $mutation = $orderLine->refresh()->subscriptionMutation;

        self::assertNotNull($mutation);
        self::assertSame(0, $mutation->net_price);

        Bus::assertDispatched(ChangeProvisioningJob::class);
    }

    #[Test]
    public function anOrdinaryUpgradeLineIsStillInvoicedAtTheProductPrice(): void
    {
        Bus::fake([ChangeProvisioningJob::class]);

        $customer = new CustomerFactory()->withAddress()->createOne();

        $hostingGroup = new ProductGroupFactory()->hosting()->createOne();
        $basicProduct = new ProductFactory()->for($hostingGroup)->createOne(['slug' => 'basic-regular-price']);
        $grootProduct = new ProductFactory()->for($hostingGroup)->createOne(['slug' => 'groot-regular-price']);

        new ProductPriceComponentFactory()
            ->prolongation()
            ->for($grootProduct)
            ->createOne(['price' => 1000]);

        $subscription = new SubscriptionFactory()
            ->for($customer)
            ->for($basicProduct)
            ->createOne();

        new ProductAllowedChangeFactory()
            ->upgradeChange()
            ->createOne([
                'from_product_id' => $basicProduct->id,
                'to_product_id' => $grootProduct->id,
            ]);

        $order = new OrderFactory()->createOne(['customer_id' => $customer->id]);

        $orderLine = new OrderLineItemFactory()
            ->withPrice()
            ->createOne([
                'order_id' => $order->id,
                'status' => OrderLineItemStatus::REGISTRATION,
                'product_uuid' => $grootProduct->uuid,
                'product_name' => $grootProduct->name,
                'subscription_uuid' => $subscription->uuid,
            ]);

        self::resolve(OrderService::class)->processMutations($order);

        $subscription->refresh();

        self::assertSame($grootProduct->uuid, $subscription->product_uuid);
        self::assertSame(1000, $subscription->net_price);
        self::assertFalse(self::resolve(PriceRepository::class)->hasIndefiniteCustomPrice($subscription));
        self::assertGreaterThan(0, Invoice::query()->where('customer_id', $customer->id)->count());

        $mutation = $orderLine->refresh()->subscriptionMutation;

        self::assertNotNull($mutation);
        self::assertSame(1000, $mutation->net_price);
    }
}
