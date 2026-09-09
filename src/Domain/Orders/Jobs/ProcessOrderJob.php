<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\Jobs;

use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\OneTimeServices\Services\OneTimeServiceCreator;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Services\OrderService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class ProcessOrderJob extends AbstractQueueableJob implements ShouldBeUnique
{
    public int $tries = 10;

    public int $uniqueFor = 900;

    public function __construct(
        private readonly Order $order,
    ) {
        parent::__construct();
    }

    public function uniqueId(): string
    {
        return $this->order->uuid;
    }

    public function failed(?Throwable $exception): void
    {
        /** @var LoggerInterface $logger */
        $logger = Container::getInstance()->make(LoggerInterface::class);

        if ($exception === null) {
            $logger->critical(
                'Error processing order with id {order.id}, job failed without exception',
                [
                    LoggingContextKeys::ORDER_ID  => $this->order->id,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            return;
        }

        $logger->critical(
            sprintf('Error processing order, job failed with exception message: %s', $exception->getMessage()),
            [
                LoggingContextKeys::ORDER_ID  => $this->order->id,
                LoggingContextKeys::EXCEPTION => $exception,
            ],
        );
    }

    public function handle(
        SubscriptionService $subscriptionService,
        OneTimeServiceCreator $oneTimeServiceCreator,
        LoggerInterface $logger,
        Dispatcher $dispatcher,
        OrderService $orderService,
    ): void {
        $logger->debug('Starting ProcessOrderJob for order {order.id} with attempt {job.attempt}/{job.max_attempts}', [
                LoggingContextKeys::ORDER_ID => $this->order->id,
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
        ]);

        if (in_array($this->order->status, [OrderStatus::PROCESSED, OrderStatus::ABUSE])) {
            return;
        }

        try {
            $orderService->processMutations($this->order);
            $subscriptionService->createSubscriptionsFromOrder($this->order);
            $oneTimeServiceCreator->createFromOrder($this->order);

            $this->order->status = OrderStatus::PROCESSED;
            $this->order->save();
        } catch (Throwable $exception) {
            $logger->critical(
                sprintf('Error processing order: %s', $exception->getMessage()),
                [
                    LoggingContextKeys::ORDER_ID  => $this->order->id,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            throw $exception;
        }
        $dispatcher->dispatch(new BillOrderJob($this->order));
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::SUBSCRIPTIONS;
    }
}
