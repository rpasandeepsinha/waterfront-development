<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs\Proxies;

use Illuminate\Bus\Dispatcher;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Ferry\Jobs\CustomerMigrationJob;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class HandleCustomerBulkPayloadJob extends AbstractQueueableJob
{
    /**
     * @var int
     */
    public $timeout = 900;

    /**
     * @param array<int, array<mixed>> $customers
     */
    public function __construct(private readonly array $customers)
    {
        parent::__construct();
    }

    public function handle(
        Dispatcher $dispatcher,
        LoggerInterface $logger
    ): void {
        $amount = count($this->customers);

        $logger->info('Starting to insert bulk customer jobs.', [
            LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
            LoggingContextKeys::META => [
                'customer_amount' => $amount,
            ],
        ]);

        foreach ($this->customers as $customerArray) {
            $dispatcher->dispatch(new CustomerMigrationJob($customerArray));
        }

        $logger->info('Completed inserting bulk customer jobs.', [
            LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
            LoggingContextKeys::META => [
                'customer_amount' => $amount,
            ],
        ]);
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::FERRY_PROXY;
    }
}
