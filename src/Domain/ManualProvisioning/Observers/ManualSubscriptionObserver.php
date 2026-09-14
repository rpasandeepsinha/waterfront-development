<?php

declare(strict_types=1);

namespace Waterfront\Domain\ManualProvisioning\Observers;

use Waterfront\Domain\ManualProvisioning\Services\ManualProvisioningService;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class ManualSubscriptionObserver
{
    public function __construct(
        private readonly ManualProvisioningService $manualSubscriptionService,
    ) {
    }

    public function updating(Subscription $subscription): void
    {
        $subscription->loadMissing(['product.productGroup']);
        if (
            $subscription->isDirty('technical_status')
            && $this->manualSubscriptionService->manualProductIsActivate($subscription)
        ) {
            $this->manualSubscriptionService->sendActivationNotification($subscription);
        }
    }
}
