<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Jobs;

use Waterfront\Domain\Subscriptions\Models\SubscriptionChange;
use Waterfront\Domain\Subscriptions\Services\ChangeProvisioningService;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class ChangeProvisioningJob extends AbstractQueueableJob
{
    public function __construct(private readonly SubscriptionChange $subscriptionChange)
    {
        parent::__construct();
    }

    public function handle(ChangeProvisioningService $changeProvisioningService): void
    {
        $changeProvisioningService->handleProvisioning($this->subscriptionChange);
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::SUBSCRIPTIONS;
    }
}
