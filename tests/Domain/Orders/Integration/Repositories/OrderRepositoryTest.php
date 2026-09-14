<?php

declare(strict_types=1);

namespace Tests\Domain\Orders\Integration\Repositories;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Repositories\OrderRepository;

#[CoversClass(OrderRepository::class)]
class OrderRepositoryTest extends IntegrationTestCase
{
    private OrderRepository $repository;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = self::resolve(OrderRepository::class);
        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function whereOrderedByMetadataSchemaIdReturnsMatchingOrders(): void
    {
        $employeeOrder = new OrderFactory()->for($this->customer)->createOne([
            'ordered_by_metadata' => json_encode(['schemaId' => 'employee', 'email' => 'emp@example.com']),
        ]);

        new OrderFactory()->for($this->customer)->createOne([
            'ordered_by_metadata' => json_encode(['schemaId' => 'customer', 'email' => 'cust@example.com']),
        ]);

        $results = $this->repository->whereOrderedByMetadataSchemaId(Order::query(), 'employee')->get();

        self::assertCount(1, $results);
        $order = $results->first();
        self::assertNotNull($order);
        self::assertSame($employeeOrder->id, $order->id);
    }

    #[Test]
    public function whereOrderedByMetadataSchemaIdExcludesNonMatchingOrders(): void
    {
        new OrderFactory()->for($this->customer)->createOne([
            'ordered_by_metadata' => json_encode(['schemaId' => 'customer', 'email' => 'cust@example.com']),
        ]);

        $results = $this->repository->whereOrderedByMetadataSchemaId(Order::query(), 'employee')->get();

        self::assertCount(0, $results);
    }

    #[Test]
    public function whereOrderedByMetadataEmailContainsReturnsMatchingOrders(): void
    {
        $matchingOrder = new OrderFactory()->for($this->customer)->createOne([
            'ordered_by_metadata' => json_encode(['schemaId' => 'employee', 'email' => 'john.doe@example.com']),
        ]);

        new OrderFactory()->for($this->customer)->createOne([
            'ordered_by_metadata' => json_encode(['schemaId' => 'employee', 'email' => 'jane.smith@example.com']),
        ]);

        $results = $this->repository->whereOrderedByMetadataEmailContains(Order::query(), 'john')->get();

        self::assertCount(1, $results);
        $order = $results->first();
        self::assertNotNull($order);
        self::assertSame($matchingOrder->id, $order->id);
    }

    #[Test]
    public function whereOrderedByMetadataEmailContainsIsCaseInsensitive(): void
    {
        $order = new OrderFactory()->for($this->customer)->createOne([
            'ordered_by_metadata' => json_encode(['schemaId' => 'employee', 'email' => 'John.Doe@Example.COM']),
        ]);

        $results = $this->repository->whereOrderedByMetadataEmailContains(Order::query(), 'JOHN')->get();

        self::assertCount(1, $results);
        $result = $results->first();
        self::assertNotNull($result);
        self::assertSame($order->id, $result->id);
    }

    #[Test]
    public function whereOrderedByMetadataEmailContainsMatchesPartialEmail(): void
    {
        $order = new OrderFactory()->for($this->customer)->createOne([
            'ordered_by_metadata' => json_encode(['schemaId' => 'employee', 'email' => 'john.doe@example.com']),
        ]);

        $results = $this->repository->whereOrderedByMetadataEmailContains(Order::query(), '@example.com')->get();

        self::assertCount(1, $results);
        $result = $results->first();
        self::assertNotNull($result);
        self::assertSame($order->id, $result->id);
    }

    #[Test]
    public function whereOrderedByMetadataEmailContainsExcludesNonMatchingOrders(): void
    {
        new OrderFactory()->for($this->customer)->createOne([
            'ordered_by_metadata' => json_encode(['schemaId' => 'employee', 'email' => 'jane.smith@example.com']),
        ]);

        $results = $this->repository->whereOrderedByMetadataEmailContains(Order::query(), 'john')->get();

        self::assertCount(0, $results);
    }

    #[Test]
    public function getSubscriptionsByOrderUuidReturnsSubscriptionsOfTheOrderLineItems(): void
    {
        $productGroup = new ProductGroupFactory()->createOne();
        $firstProduct = new ProductFactory()->for($productGroup)->createOne();
        $secondProduct = new ProductFactory()->for($productGroup)->createOne();

        $firstSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($firstProduct)
            ->createOne();
        $secondSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($secondProduct)
            ->createOne();

        $order = new OrderFactory()->for($this->customer)->createOne();
        new OrderLineItemFactory()->createOne([
            'order_id' => $order->id,
            'subscription_uuid' => $firstSubscription->uuid,
        ]);
        new OrderLineItemFactory()->createOne([
            'order_id' => $order->id,
            'subscription_uuid' => $secondSubscription->uuid,
        ]);

        $subscriptions = $this->repository->getSubscriptionsByOrderUuid(Uuid::fromString($order->uuid));

        self::assertCount(2, $subscriptions);
        self::assertEqualsCanonicalizing(
            [$firstSubscription->uuid, $secondSubscription->uuid],
            $subscriptions->pluck('uuid')->all(),
        );
    }

    #[Test]
    public function getSubscriptionsByOrderUuidEagerLoadsTheProductAndProductGroup(): void
    {
        $productGroup = new ProductGroupFactory()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->createOne();

        $order = new OrderFactory()->for($this->customer)->createOne();
        new OrderLineItemFactory()->createOne([
            'order_id' => $order->id,
            'subscription_uuid' => $subscription->uuid,
        ]);

        $foundSubscription = $this->repository->getSubscriptionsByOrderUuid(Uuid::fromString($order->uuid))->first();

        self::assertNotNull($foundSubscription);
        self::assertTrue($foundSubscription->relationLoaded('product'));
        self::assertTrue($foundSubscription->product->relationLoaded('productGroup'));
        self::assertSame($productGroup->id, $foundSubscription->product->productGroup->id);
    }

    #[Test]
    public function getSubscriptionsByOrderUuidExcludesSubscriptionsOfOtherOrders(): void
    {
        $productGroup = new ProductGroupFactory()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();
        $otherProduct = new ProductFactory()->for($productGroup)->createOne();

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->createOne();
        $otherSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($otherProduct)
            ->createOne();

        $order = new OrderFactory()->for($this->customer)->createOne();
        new OrderLineItemFactory()->createOne([
            'order_id' => $order->id,
            'subscription_uuid' => $subscription->uuid,
        ]);

        $otherOrder = new OrderFactory()->for($this->customer)->createOne();
        new OrderLineItemFactory()->createOne([
            'order_id' => $otherOrder->id,
            'subscription_uuid' => $otherSubscription->uuid,
        ]);

        $subscriptions = $this->repository->getSubscriptionsByOrderUuid(Uuid::fromString($order->uuid));

        self::assertCount(1, $subscriptions);
        $foundSubscription = $subscriptions->first();
        self::assertNotNull($foundSubscription);
        self::assertSame($subscription->uuid, $foundSubscription->uuid);
    }

    #[Test]
    public function getSubscriptionsByOrderUuidSkipsLineItemsWithoutSubscription(): void
    {
        $productGroup = new ProductGroupFactory()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->createOne();

        $order = new OrderFactory()->for($this->customer)->createOne();
        new OrderLineItemFactory()->createOne([
            'order_id' => $order->id,
            'subscription_uuid' => $subscription->uuid,
        ]);
        new OrderLineItemFactory()->createOne([
            'order_id' => $order->id,
            'subscription_uuid' => null,
        ]);

        $subscriptions = $this->repository->getSubscriptionsByOrderUuid(Uuid::fromString($order->uuid));

        self::assertCount(1, $subscriptions);
        $foundSubscription = $subscriptions->first();
        self::assertNotNull($foundSubscription);
        self::assertSame($subscription->uuid, $foundSubscription->uuid);
    }

    #[Test]
    public function getSubscriptionsByOrderUuidReturnsEmptyCollectionForOrderWithoutLineItems(): void
    {
        $order = new OrderFactory()->for($this->customer)->createOne();

        self::assertCount(0, $this->repository->getSubscriptionsByOrderUuid(Uuid::fromString($order->uuid)));
    }

    #[Test]
    public function getSubscriptionsByOrderUuidReturnsEmptyCollectionForUnknownOrder(): void
    {
        self::assertCount(0, $this->repository->getSubscriptionsByOrderUuid(Uuid::uuid4()));
    }
}
