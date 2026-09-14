<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs\Proxies;

use Illuminate\Bus\Dispatcher;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Ferry\Jobs\SubscriptionMigrationJob;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class HandleSubscriptionBulkPayloadJob extends AbstractQueueableJob
{
    /**
     * @var int
     */
    public $timeout = 900;

    /**
     * @param array<int, array<mixed>> $subscriptions
     */
    public function __construct(
        private readonly array $subscriptions,
    ) {
        parent::__construct();
    }

    public function handle(
        Dispatcher $dispatcher,
        LoggerInterface $logger,
    ): void {
        $amount = count($this->subscriptions);

        $logger->info('Starting to insert bulk subscription jobs.', [
            LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
            LoggingContextKeys::META => [
                'subscription_amount' => $amount,
            ],
        ]);

        foreach ($this->subscriptions as $subscriptionArray) {
            $dispatcher->dispatch(new SubscriptionMigrationJob($subscriptionArray));
        }

        $logger->info('Completed inserting bulk subscription jobs.', [
            LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
            LoggingContextKeys::META => [
                'subscription_amount' => $amount,
            ],
        ]);
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::FERRY_PROXY;
    }
}
