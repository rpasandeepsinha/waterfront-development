<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Subscription;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Models\SubscriptionChange;

/**
 * @property SubscriptionChange $resource
 */
class SubscriptionChangesResource extends JsonResource
{
    /**
     * @return array<mixed>
     */
    public function toArray(Request $request): array
    {
        $fromProduct = $this->resource->fromProduct()->first();
        $toProduct = $this->resource->toProduct()->first();
        assert($fromProduct instanceof Product);
        assert($toProduct instanceof Product);

        return [
            'id' => $this->resource->id,
            'subscription_id' => $this->resource->subscription_uuid,
            'from_product_name' => $fromProduct->name,
            'to_product_name' => $toProduct->name,
            'type' => $this->resource->type,
            'status' => $this->resource->status,
            'failure_code' => $this->resource->failure_code,
            'failure_message' => $this->resource->failure_message,
            'requested_at' => $this->resource->requested_at,
            'completed_at' => $this->resource->completed_at,
        ];
    }
}
