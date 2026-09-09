<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Atlantis\Resources\Products;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Products\Models\ProductPromotion;

/**
 * @property-read ProductPromotion $resource
 */
class ProductPromotionResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'product' => $this->resource->product,
            'callToAction' => $this->resource->call_to_action,
            'platform' => $this->resource->platform,
            'weight' => $this->resource->weight,
            'start_date' => $this->resource->start_date,
            'end_date' => $this->resource->end_date,
            'placement_url' => $this->resource->placement_url,
        ];
    }
}
