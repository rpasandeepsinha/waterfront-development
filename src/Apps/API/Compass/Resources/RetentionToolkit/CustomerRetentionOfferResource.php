<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\RetentionToolkit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\RetentionToolkit\Models\CustomerRetentionOffer;

/** @property CustomerRetentionOffer $resource */
class CustomerRetentionOfferResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $createdByMetadata = $this->resource->created_by_metadata;

        return [
            'created_by_metadata' => $createdByMetadata === null ? null : [
                'uuid' => $createdByMetadata->uuid->toString(),
                'email' => $createdByMetadata->email,
            ],
            'customer_type' => $this->resource->customer_type->value,
            'selected_action' => $this->resource->selected_action->value,
            'puzzel_ticket_id' => $this->resource->puzzel_ticket_id,
            'credit_amount' => $this->resource->credit_amount,
            'discount_amount' => $this->resource->discount_amount,
            'effective_at' => $this->resource->effective_at->toW3cString(),
            'created_at' => $this->resource->created_at?->toW3cString(),
            'updated_at' => $this->resource->updated_at?->toW3cString(),
        ];
    }
}
