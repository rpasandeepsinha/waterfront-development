<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Infrastructure;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Servers\Models\LegacyRedirectingServer;

/** @property LegacyRedirectingServer $resource */
class LegacyRedirectingServerResource extends JsonResource
{
    /** @return array<string, int|string|null> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'hostname' => $this->resource->hostname,
            'ipv4' => $this->resource->ipv4,
            'ipv6' => $this->resource->ipv6,
            'original_business_unit' => $this->resource->original_business_unit,
        ];
    }
}
