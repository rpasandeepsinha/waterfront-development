<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Transfers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * @property Subscription $resource
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'domain' => $this->resource->domain,
            'product_name' => $this->resource->product->name,
            'start_date' => $this->resource->start_date,
            'end_date' => $this->resource->end_date,
            'executed_at' => $this->resource->pivot->executed_at,
            'failed_at' => $this->resource->pivot->failed_at,
            'reason_failed' => $this->resource->pivot->reason_failed,
            'administrative_status' => $this->resource->administrative_status,
        ];
    }
}
