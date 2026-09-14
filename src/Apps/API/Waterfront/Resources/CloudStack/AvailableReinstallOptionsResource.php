<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources\CloudStack;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Products\Models\Product;

class AvailableReinstallOptionsResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var Product $product */
        $product = $this->resource;

        return [
            'uuid' => $product->uuid,
            'name' => $product->name,
            'specs' => $product->productSpecs->pluck('value', 'name')->toArray(),
        ];
    }
}
