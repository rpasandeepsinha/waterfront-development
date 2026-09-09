<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Infrastructure;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\DNS\Models\DnsNameserver;

/** @property DnsNameserver $resource */
class NameserverResource extends JsonResource
{
    /** @return array<string, bool|int|string|null> */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'nameserver' => $this->resource->nameserver,
            'region' => $this->resource->dnsRegion->name,
        ];
    }
}
