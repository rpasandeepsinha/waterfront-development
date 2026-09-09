<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Observers;

use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;

class ProductObserver
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
    }

    public function updated(Product $product): void
    {
        if ($product->isDirty(['name']) || $product->isDirty(['slug'])) {
            // Update subscriptions so they get synced to HubSpot
            $this->subscriptionRepository->updateChangedTimeByProduct($product);
        }
    }
}
