<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Infrastructure;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\DNS\Models\DnsTemplateRecordSet;

/** @property DnsTemplateRecordSet $resource */
class DnsRecordResource extends JsonResource
{
    /** @return array<string, int|string|null> */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'type' => $this->resource->type,
            'ttl' => $this->resource->ttl,
        ];
    }
}
