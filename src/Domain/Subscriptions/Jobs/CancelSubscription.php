<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Jobs;

use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\CancellationService;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class CancelSubscription extends AbstractQueueableJob
{
    public function __construct(private readonly Subscription $subscription)
    {
        parent::__construct();
    }

    public function handle(CancellationService $cancellationService): void
    {
        $cancellationService->cancel(
            $this->subscription,
            SubscriptionCancelType::CANCEL_END_DATE,
            SubscriptionCancelReason::REASON_ABUSE,
            false,
            null,
            null,
            false,
        );
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::SUBSCRIPTIONS;
    }
}
