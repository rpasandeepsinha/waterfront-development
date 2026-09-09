<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Search;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Apps\API\Compass\Resources\Enum\SearchType;
use Waterfront\Domain\Products\Models\Product;

/**
 * @property Product $resource
 */
class SearchProductResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, int|string|null>
     */
    public function toArray($request): array
    {
        return [
            'type' => SearchType::PRODUCT->value,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'uuid' => $this->resource->uuid,
            'product_group' => $this->resource->productGroup->name,
        ];
    }
}
