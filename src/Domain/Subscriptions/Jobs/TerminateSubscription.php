<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Jobs;

use Waterfront\Domain\Subscriptions\Exceptions\TerminateException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionTerminateService;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class TerminateSubscription extends AbstractQueueableJob
{
    public function __construct(
        public readonly Subscription $subscription,
    ) {
        parent::__construct();
    }

    /**
     * @throws TerminateException
     */
    public function handle(SubscriptionTerminateService $service): void
    {
        $service->terminate($this->subscription);
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::SUBSCRIPTIONS;
    }
}
