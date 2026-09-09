<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Observers;

use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;

class ProductGroupObserver
{
    public function __construct(private readonly SubscriptionRepository $subscriptionRepository)
    {
    }

    public function updated(ProductGroup $productGroup): void
    {
        if ($productGroup->isDirty(['name']) || $productGroup->isDirty(['slug'])) {
            // Update subscriptions so they get synced to HubSpot
            $this->subscriptionRepository->updateChangedTimeByProductGroup($productGroup);
        }
    }
}
