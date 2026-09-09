<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Domains;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Domains\Models\DomainContact;

/** @property DomainContact $resource */
class DomainContactResource extends JsonResource
{
    /** @return array<string, int|string|null> */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => sprintf('%s %s', $this->resource->first_name, $this->resource->last_name),
            'email' => $this->resource->email,
            'phone' => sprintf('%s %s %s', $this->resource->phone_country_code, $this->resource->phone_area_code, $this->resource->phone_subscriber_number),
            'street' => sprintf('%s %s', $this->resource->street_name, $this->resource->street_number),
            'city' => $this->resource->city,
            'zip_code' => $this->resource->zip_code,
            'country_code' => $this->resource->country_code,
        ];
    }
}
