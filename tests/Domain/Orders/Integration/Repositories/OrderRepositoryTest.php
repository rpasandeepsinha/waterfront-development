<?php

declare(strict_types=1);

namespace Tests\Domain\Orders\Integration\Repositories;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OrderFactory;
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

        $results = $this->repository
            ->whereOrderedByMetadataSchemaId(Order::query(), 'employee')
            ->get();

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

        $results = $this->repository
            ->whereOrderedByMetadataSchemaId(Order::query(), 'employee')
            ->get();

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

        $results = $this->repository
            ->whereOrderedByMetadataEmailContains(Order::query(), 'john')
            ->get();

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

        $results = $this->repository
            ->whereOrderedByMetadataEmailContains(Order::query(), 'JOHN')
            ->get();

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

        $results = $this->repository
            ->whereOrderedByMetadataEmailContains(Order::query(), '@example.com')
            ->get();

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

        $results = $this->repository
            ->whereOrderedByMetadataEmailContains(Order::query(), 'john')
            ->get();

        self::assertCount(0, $results);
    }
}
