<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Jobs;

use Override;
use Waterfront\Domain\Subscriptions\Actions\DetermineExpirationPathForSubscriptionAction;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;
use Webmozart\Assert\Assert;

class ExpireSubscriptionJob extends AbstractQueueableJob
{
    public function __construct(
        private readonly Subscription $subscription,
    ) {
        Assert::null($subscription->parent_subscription_id, 'Only parent subscription is allowed');

        parent::__construct();
    }

    public function handle(DetermineExpirationPathForSubscriptionAction $action): void
    {
        $action->execute($this->subscription);
    }

    #[Override]
    protected function getQueueName(): QueueName
    {
        return QueueName::SUBSCRIPTIONS;
    }
}
