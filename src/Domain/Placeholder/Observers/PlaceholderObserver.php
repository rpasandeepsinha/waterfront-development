<?php

declare(strict_types=1);

namespace Waterfront\Domain\Placeholder\Observers;

use Waterfront\Domain\ManualProvisioning\Services\ManualProvisioningService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class PlaceholderObserver
{
    public function __construct(
        private readonly ManualProvisioningService $manualSubscriptionService
    ) {
    }

    public function updating(Subscription $subscription): void
    {
        if ($subscription->technical_status !== TechnicalStatus::OK->value) {
            return;
        }

        if (! $subscription->isDirty('technical_status')) {
            return;
        }

        if ($subscription->getOriginal('technical_status') !== TechnicalStatus::PENDING->value) {
            return;
        }

        if ($subscription->hostingDeployment?->provider?->slug !== ProviderSlug::PLACEHOLDER &&
            $subscription->sslDeployment?->provider->slug !== ProviderSlug::PLACEHOLDER &&
            $subscription->domainDeployment?->provider->slug !== ProviderSlug::PLACEHOLDER) {
            return;
        }

        $this->manualSubscriptionService->sendActivationNotification($subscription);
    }
}
