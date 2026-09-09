<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Domains;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Domains\DTO\RetrieveResult;

/** @property RetrieveResult|mixed[] $resource */
class DomainNameserversResource extends JsonResource
{
    /** @return array<string, array<int,string|null>|null> */
    public function toArray($request): array
    {
        return [
            'nameservers' => is_array($this->resource) ? [] : $this->resource->getNameServers(),
        ];
    }
}
