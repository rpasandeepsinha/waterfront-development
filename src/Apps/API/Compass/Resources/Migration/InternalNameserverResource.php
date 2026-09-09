<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Migration;

use Carbon\CarbonInterface;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Ferry\Models\FerryInternalNameserver;

/** @property FerryInternalNameserver $resource */
class InternalNameserverResource extends JsonResource
{
    /** @return array{id: int, nameserver_hostname: string, created_at: ?CarbonInterface, updated_at: ?CarbonInterface} */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'nameserver_hostname' => $this->resource->nameserver_hostname,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
