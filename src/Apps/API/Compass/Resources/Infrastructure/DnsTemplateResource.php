<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Infrastructure;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\DNS\Models\DnsTemplate;

/** @property DnsTemplate $resource */
class DnsTemplateResource extends JsonResource
{
    /** @return array<string, AnonymousResourceCollection|int|string|null> */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'slug' => $this->resource->slug,
            'recordSets' => DnsRecordResource::collection($this->resource->recordSets),
        ];
    }
}
