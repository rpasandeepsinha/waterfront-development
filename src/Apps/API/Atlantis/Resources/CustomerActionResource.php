<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Atlantis\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\CustomerActionNeeded\DTO\CustomerActionNeeded;

/** @property-read CustomerActionNeeded $resource */
class CustomerActionResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'title' => $this->resource->title,
            'message' => $this->resource->message,
            'slug' => $this->resource->slug->value,
            'productGroupSlug' => $this->resource->productGroupSlug?->value,
            'productSlug' => $this->resource->productSlug,
        ];
    }
}
