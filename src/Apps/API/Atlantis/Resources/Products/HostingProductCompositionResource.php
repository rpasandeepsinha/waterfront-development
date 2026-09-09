<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Atlantis\Resources\Products;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Products\Models\HostingProductComposition;

/** @property-read HostingProductComposition $resource */
class HostingProductCompositionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'composedProductSlug' => $this->resource->composedProduct->slug,
            'wpComposedProductSlug' => $this->resource->wpComposedProduct?->slug,
            'mailOnlyProductSlug' => $this->resource->mailOnlyProduct->slug,
            'webOnlyProductSlug' => $this->resource->webOnlyProduct->slug,
            'wpWebOnlyProductSlug' => $this->resource->wpWebOnlyProduct?->slug,
        ];
    }
}
