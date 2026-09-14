<?php

declare(strict_types=1);

namespace Tests\Domain\OneTimeServices\Services;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OneTimeServiceFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\PaymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Invoices\DTO\OneTimeServiceContext;
use Waterfront\Domain\Notes\Actions\StoreNoteAction;
use Waterfront\Domain\OneTimeServices\Repositories\OneTimeServiceRepository;
use Waterfront\Domain\OneTimeServices\Services\GrossPriceResolver;
use Waterfront\Domain\OneTimeServices\Services\OneTimeServiceCreator;
use Waterfront\Domain\Payments\Enums\PaymentStatus;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(OneTimeServiceCreator::class)]
#[AllowMockObjectsWithoutExpectations]
class OneTimeServiceCreatorTest extends IntegrationTestCase
{
    private OneTimeServiceCreator $oneTimeServiceCreator;

    private LoggerInterface&MockObject $logger;

    private OneTimeServiceRepository&MockObject $oneTimeServiceRepository;

    private Product $parentProduct;

    private Product $oneTimeServiceProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->oneTimeServiceRepository = $this->createMock(OneTimeServiceRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->oneTimeServiceCreator = new OneTimeServiceCreator(
            oneTimeServiceRepository: $this->oneTimeServiceRepository,
            grossPriceResolver: $this->createStub(GrossPriceResolver::class),
            logger: $this->logger,
            storeNoteAction: self::createMock(StoreNoteAction::class),
        );

        $this->parentProduct = new ProductFactory()->for(new ProductGroupFactory()->ssl())->createOne();
        $this->oneTimeServiceProduct = new ProductFactory()->for(
            new ProductGroupFactory()->oneTimeService(),
        )->createOne();
    }

    #[Test]
    public function expectExceptionWhenMissingProduct(): void
    {
        $order = new OrderFactory()->for(new CustomerFactory()->createOne())->createOne();

        $orderLineItem = new OrderLineItemFactory()->for($order)->createOne(
            [
                'parent_id' => null,
                'subscription_uuid' => null,
                'status' => 'registration',
            ],
        );
        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Creating one time services from order {order.id}',
                [
                    LoggingContextKeys::ORDER_ID => $order->id,
                ],
            );

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Product is required to create a one time service for order line #{order_line.id} for order #{order.id}.',
                [
                    LoggingContextKeys::ORDER_ID => $order->id,
                    LoggingContextKeys::ORDER_LINE_ID => $orderLineItem->id,
                ],
            );

        self::expectException(RuntimeException::class);
        $this->oneTimeServiceCreator->createFromOrder($order);
        $orderLineItem->refresh();
        self::assertNull($orderLineItem->one_time_service_id);
    }

    #[Test]
    public function expectExceptionWhenMissingParent(): void
    {
        $order = new OrderFactory()->for(new CustomerFactory()->createOne())->createOne();

        $orderLineItem = new OrderLineItemFactory()
            ->for($order)
            ->for($this->oneTimeServiceProduct)
            ->createOne(
                [
                    'parent_id' => null,
                    'subscription_uuid' => null,
                    'status' => 'registration',
                ],
            );
        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Creating one time services from order {order.id}',
                [
                    LoggingContextKeys::ORDER_ID => $order->id,
                ],
            );

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Parent subscription missing for one time service for order line #{order_line.id} for order #{order.id}.',
                [
                    LoggingContextKeys::ORDER_ID => $order->id,
                    LoggingContextKeys::ORDER_LINE_ID => $orderLineItem->id,
                    LoggingContextKeys::PRODUCT_ID => $orderLineItem->product?->id,
                    LoggingContextKeys::PRODUCT_SLUG => $orderLineItem->product?->slug,
                ],
            );

        self::expectException(RuntimeException::class);
        $this->oneTimeServiceCreator->createFromOrder($order);
        $orderLineItem->refresh();
        self::assertNull($orderLineItem->one_time_service_id);
    }

    #[Test]
    public function expectExceptionWhenSubscriptionNotYetCreated(): void
    {
        $order = new OrderFactory()->for(new CustomerFactory()->createOne())->createOne();

        $parentOrderLine = new OrderLineItemFactory()
            ->for($order)
            ->for($this->parentProduct)
            ->createOne(
                [
                    'subscription_uuid' => null,
                    'status' => 'registration',
                ],
            );
        $orderLineItem = new OrderLineItemFactory()
            ->for($order)
            ->for($this->oneTimeServiceProduct)
            ->createOne(
                [
                    'parent_id' => $parentOrderLine->id,
                    'subscription_uuid' => null,
                    'status' => 'registration',
                ],
            );
        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Could not create one time service for order line #{order_line.id} for order #{order.id} because of missing subscription. Subscription should be created first.',
                [
                    LoggingContextKeys::ORDER_ID => $order->id,
                    LoggingContextKeys::ORDER_LINE_ID => $orderLineItem->id,
                    LoggingContextKeys::PRODUCT_ID => $orderLineItem->product?->id,
                    LoggingContextKeys::PRODUCT_SLUG => $orderLineItem->product?->slug,
                ],
            );

        self::expectException(RuntimeException::class);
        $this->oneTimeServiceCreator->createFromOrder($order);
        $orderLineItem->refresh();
        self::assertNull($orderLineItem->one_time_service_id);
    }

    #[Test]
    public function createOneTimeServiceWithoutPayment(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::now());
        $customer = new CustomerFactory()->createOne();
        $order = new OrderFactory()->for($customer)->createOne();

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->parentProduct)
            ->createOne();

        $parentOrderLine = new OrderLineItemFactory()
            ->for($order)
            ->for($this->parentProduct)
            ->for($subscription)
            ->createOne(
                [
                    'status' => 'registration',
                ],
            );
        $orderLineItem = new OrderLineItemFactory()
            ->for($order)
            ->for($this->oneTimeServiceProduct)
            ->createOne(
                [
                    'parent_id' => $parentOrderLine->id,
                    'subscription_uuid' => null,
                    'status' => 'registration',
                    'gross_price' => 101,
                    'net_price' => 50,
                ],
            );
        $oneTimeService = new OneTimeServiceFactory()
            ->for($customer)
            ->for($subscription)
            ->createOne(
                [
                    'product_id' => $this->oneTimeServiceProduct->id,
                ],
            );

        $this->oneTimeServiceRepository
            ->expects(self::once())
            ->method('create')
            ->with(self::callback(fn (OneTimeServiceContext $context) => $context->discountPercentage === 50), 101)
            ->willReturn($oneTimeService);

        $this->oneTimeServiceCreator->createFromOrder($order);

        $orderLineItem->refresh();
        self::assertSame($oneTimeService->id, $orderLineItem->one_time_service_id);
        self::assertNotNull($orderLineItem->processed_at);
        self::assertSame(CarbonImmutable::now()->toDateTimeString(), $orderLineItem->processed_at);
    }

    #[Test]
    public function createOneTimeServiceWithoutPaymentAndFullDiscount(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::now());
        $customer = new CustomerFactory()->createOne();
        $order = new OrderFactory()->for($customer)->createOne();

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->parentProduct)
            ->createOne();

        $parentOrderLine = new OrderLineItemFactory()
            ->for($order)
            ->for($this->parentProduct)
            ->for($subscription)
            ->createOne(
                [
                    'status' => 'registration',
                ],
            );
        new OrderLineItemFactory()
            ->for($order)
            ->for($this->oneTimeServiceProduct)
            ->createOne(
                [
                    'parent_id' => $parentOrderLine->id,
                    'subscription_uuid' => null,
                    'status' => 'registration',
                    'gross_price' => 101,
                    'net_price' => 0,
                ],
            );

        $this->oneTimeServiceRepository
            ->expects(self::once())
            ->method('create')
            ->with(self::callback(fn (OneTimeServiceContext $context) => $context->discountPercentage === 100), 101);

        $this->oneTimeServiceCreator->createFromOrder($order);
    }

    #[Test]
    public function createOneTimeServiceWithoutPaymentAndFreeProduct(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::now());
        $customer = new CustomerFactory()->createOne();
        $order = new OrderFactory()->for($customer)->createOne();

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->parentProduct)
            ->createOne();

        $parentOrderLine = new OrderLineItemFactory()
            ->for($order)
            ->for($this->parentProduct)
            ->for($subscription)
            ->createOne(
                [
                    'status' => 'registration',
                ],
            );
        new OrderLineItemFactory()
            ->for($order)
            ->for($this->oneTimeServiceProduct)
            ->createOne(
                [
                    'parent_id' => $parentOrderLine->id,
                    'subscription_uuid' => null,
                    'status' => 'registration',
                    'gross_price' => 0,
                    'net_price' => 0,
                ],
            );

        $this->oneTimeServiceRepository
            ->expects(self::once())
            ->method('create')
            ->with(self::callback(fn (OneTimeServiceContext $context) => $context->discountPercentage === 0), 0);

        $this->oneTimeServiceCreator->createFromOrder($order);
    }

    #[Test]
    public function createOneTimeServiceWithPayment(): void
    {
        $customer = new CustomerFactory()->createOne();
        $order = new OrderFactory()->for($customer)->createOne();
        new PaymentFactory()
            ->for($order)
            ->for($customer)
            ->createOne([
                'status' => PaymentStatus::PAID,
            ]);

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->parentProduct)
            ->createOne();

        $parentOrderLine = new OrderLineItemFactory()
            ->for($order)
            ->for($this->parentProduct)
            ->for($subscription)
            ->createOne(
                [
                    'status' => 'registration',
                ],
            );
        $orderLineItem = new OrderLineItemFactory()
            ->for($order)
            ->for($this->oneTimeServiceProduct)
            ->createOne(
                [
                    'parent_id' => $parentOrderLine->id,
                    'subscription_uuid' => null,
                    'status' => 'registration',
                ],
            );
        $oneTimeService = new OneTimeServiceFactory()
            ->for($customer)
            ->for($subscription)
            ->createOne(
                [
                    'product_id' => $this->oneTimeServiceProduct->id,
                ],
            );

        $this->oneTimeServiceRepository->expects(self::once())->method('create')->willReturn($oneTimeService);

        $this->oneTimeServiceCreator->createFromOrder($order);

        $orderLineItem->refresh();
        self::assertSame($oneTimeService->id, $orderLineItem->one_time_service_id);
    }

    #[Test]
    public function oneTimeServiceAlreadyCreated(): void
    {
        $customer = new CustomerFactory()->createOne();
        $order = new OrderFactory()->for($customer)->createOne();

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->parentProduct)
            ->createOne();

        $parentOrderLine = new OrderLineItemFactory()
            ->for($order)
            ->for($this->parentProduct)
            ->for($subscription)
            ->createOne(
                [
                    'status' => 'registration',
                ],
            );
        $oneTimeService = new OneTimeServiceFactory()
            ->for($customer)
            ->for($subscription)
            ->createOne(
                [
                    'product_id' => $this->oneTimeServiceProduct->id,
                ],
            );

        $orderLineItem = new OrderLineItemFactory()
            ->for($order)
            ->for($this->oneTimeServiceProduct)
            ->createOne(
                [
                    'parent_id' => $parentOrderLine->id,
                    'subscription_uuid' => null,
                    'status' => 'registration',
                    'one_time_service_id' => $oneTimeService->id,
                ],
            );
        $this->oneTimeServiceRepository->expects(self::never())->method('create')->willReturn($oneTimeService);

        $this->oneTimeServiceCreator->createFromOrder($order);

        $orderLineItem->refresh();
        self::assertSame($oneTimeService->id, $orderLineItem->one_time_service_id);
    }
}
