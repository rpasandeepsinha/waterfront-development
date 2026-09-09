<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Subscription\Cancellation;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/** @property Subscription $resource */
class AffectedSubscriptionResource extends JsonResource
{
    /** @return array<mixed> */
    public function toArray($request): array
    {
        $subscription = $this->resource;

        return [
            'id' => $subscription->id,
            'uuid' => $subscription->uuid,
            'domain' => $subscription->domain,
            'product' => [
                'name' => $subscription->product->name,
                'slug' => $subscription->product->slug,
            ],
            'administrative_status' => $subscription->administrative_status,
            'end_date' => $subscription->end_date->toW3cString(),
            'parent_subscription_id' => $subscription->parent_subscription_id,
        ];
    }
}
