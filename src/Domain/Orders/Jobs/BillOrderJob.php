<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\Jobs;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Orders\Exceptions\BillOrderException;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Services\OrderBiller;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class BillOrderJob extends AbstractQueueableJob implements ShouldBeUnique
{
    public int $timeout = 900;

    public int $uniqueFor = 900;

    public int $tries = 8;

    /** @var array<int> */
    public array $backoff = [60, 2 * 60, 10 * 60, 30 * 60, 60 * 60, 5 * 60 * 60];

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
        $logger = Container::getInstance()->make(LoggerInterface::class);

        if ($exception === null) {
            $logger->critical(
                'Error billing a order with id {order.id}, job failed without exception',
                [
                    LoggingContextKeys::ORDER_ID  => $this->order->id,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
                ],
            );

            return;
        }

        $logger->critical(
            sprintf('Error billing a order, job failed with exception message: %s', $exception->getMessage()),
            [
                LoggingContextKeys::ORDER_ID  => $this->order->id,
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
            ],
        );
    }

    public function handle(
        LoggerInterface $logger,
        OrderBiller $orderBiller
    ): void {
        $logger->debug(
            'Starting BillOrderJob for order {order.id} with attempt {job.attempt}/{job.max_attempts}',
            [
                LoggingContextKeys::ORDER_ID => $this->order->id,
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
            ]
        );

        try {
            $orderBiller->bill($this->order);
        } catch (BillOrderException $exception) {
            $logger->critical(
                'Failed billing of order {order.id}: ' . $exception->getMessage(),
                [
                    LoggingContextKeys::ORDER_ID => $this->order->id,
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
                ]
            );
        }
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::SUBSCRIPTIONS;
    }
}
