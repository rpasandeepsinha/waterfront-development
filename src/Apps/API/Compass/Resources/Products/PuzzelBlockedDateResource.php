<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Products;

use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Puzzel\Models\PuzzelBlockedDate;

/** @property PuzzelBlockedDate $resource */
class PuzzelBlockedDateResource extends JsonResource
{
    /** @return array{id: int, date: CarbonImmutable, reason: ?string} */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'date' => $this->resource->date,
            'reason' => $this->resource->reason,
        ];
    }
}
