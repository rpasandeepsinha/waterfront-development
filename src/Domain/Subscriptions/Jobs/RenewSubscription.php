<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Jobs;

use Illuminate\Contracts\Events\Dispatcher;
use Throwable;
use Waterfront\Domain\Subscriptions\Events\SubscriptionRenewedEvent;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionRenewService;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class RenewSubscription extends AbstractQueueableJob
{
    /* 15 minutes */
    public int $timeout = 900;

    public function __construct(private readonly Subscription $subscription)
    {
        parent::__construct();
    }

    /**
     * @throws Throwable
     */
    public function handle(SubscriptionRenewService $subscriptionRenewService, Dispatcher $eventDispatcher): void
    {
        $this->subscription->refresh();

        $subscriptionRenewService->renew($this->subscription);

        $eventDispatcher->dispatch(new SubscriptionRenewedEvent($this->subscription));
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::SUBSCRIPTIONS;
    }
}
