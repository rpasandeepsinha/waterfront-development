<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Atlantis\Resources\Products;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Experiment\Models\Experiment;

/**
 * @property-read Experiment $resource
 */
class ExperimentResource extends JsonResource
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
            'slug' => $this->resource->slug,
            'products' => $this->resource->products->pluck('uuid')->sort()->values()->toArray(),
        ];
    }
}
