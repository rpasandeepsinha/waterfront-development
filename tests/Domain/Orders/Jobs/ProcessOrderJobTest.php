<?php

declare(strict_types=1);

namespace Tests\Domain\Orders\Jobs;

use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OrderFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\OneTimeServices\Services\OneTimeServiceCreator;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Orders\Jobs\BillOrderJob;
use Waterfront\Domain\Orders\Jobs\ProcessOrderJob;
use Waterfront\Domain\Orders\Services\OrderService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;

#[CoversClass(ProcessOrderJob::class)]
class ProcessOrderJobTest extends IntegrationTestCase
{
    #[Test]
    public function handleWillDispatchBillProcessedOrderJob(): void
    {
        $order = new OrderFactory()->for(new CustomerFactory())->createOne();
        $processOrderJob = new ProcessOrderJob($order);

        $subscriptionService = self::createMock(SubscriptionService::class);
        $subscriptionService->expects(self::once())
            ->method('createSubscriptionsFromOrder')
            ->with($order);

        $orderService = self::createMock(OrderService::class);
        $orderService->expects(self::once())
            ->method('processMutations')
            ->with($order);

        $oneTimeServiceCreator = self::createMock(OneTimeServiceCreator::class);
        $oneTimeServiceCreator->expects(self::once())
            ->method('createFromOrder')
            ->with($order);
        $logger = self::resolve(LoggerInterface::class);
        $dispatcher = self::createMock(Dispatcher::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(new BillOrderJob($order));

        $processOrderJob->handle(
            $subscriptionService,
            $oneTimeServiceCreator,
            $logger,
            $dispatcher,
            $orderService,
        );

        self::assertSame(OrderStatus::PROCESSED, $order->status);
    }

    #[DataProvider('invalidStatus')]
    #[Test]
    public function handleWillNotProcessOrderWhenStatusNotValid(OrderStatus $status): void
    {
        $order = new OrderFactory()->for(new CustomerFactory())->createOne(['status' => $status]);
        $processOrderJob = new ProcessOrderJob($order);

        $subscriptionService = self::createMock(SubscriptionService::class);
        $subscriptionService->expects(self::never())->method('createSubscriptionsFromOrder');

        $oneTimeServiceCreator = self::createMock(OneTimeServiceCreator::class);
        $oneTimeServiceCreator->expects(self::never())->method('createFromOrder');

        $dispatcher = self::createMock(Dispatcher::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $orderService = self::createMock(OrderService::class);
        $orderService->expects(self::never())->method('processMutations');

        $processOrderJob->handle(
            $subscriptionService,
            $oneTimeServiceCreator,
            self::resolve(LoggerInterface::class),
            $dispatcher,
            $orderService
        );

        self::assertSame($status, $order->status);
    }

    /**
     * *_index keys are used to bind entities together using the array indexes as reference.
     *
     * @return iterable<string, array<string,orderStatus>>
     *
     * @see CreditAndDispatchInvoiceLinesToHarborTest::testCredit()
     */
    public static function invalidStatus(): iterable
    {
        yield 'Handle will not process order with status processed' => [
            'status' => orderStatus::PROCESSED,
        ];
        yield 'Handle will not process order with status abuse' => [
            'status' => orderStatus::ABUSE,
        ];
    }
}
