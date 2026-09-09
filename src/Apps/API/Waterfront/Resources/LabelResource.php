<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Subscriptions\Models\Label;

/**
 * @property-read Label $resource
 */
class LabelResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'label' => $this->resource->value,
            'id' => $this->resource->id,
        ];
    }
}
