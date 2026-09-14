<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Jobs;

use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class UpdateTechnicalStatusJob extends AbstractQueueableJob
{
    public function __construct(
        private readonly Subscription $subscription,
        private readonly string $status = TechnicalStatus::OK->value,
    ) {
        parent::__construct();
    }

    public function handle(SubscriptionRepository $subscriptionRepository): void
    {
        $subscriptionRepository->setTechnicalStatus(
            $this->subscription,
            $this->status,
        );
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::SUBSCRIPTIONS;
    }
}
