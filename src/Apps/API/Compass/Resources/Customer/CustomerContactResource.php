<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Customer;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Models\CustomerContact;

/** @property CustomerContact $resource */
class CustomerContactResource extends JsonResource
{
    /** @return array<mixed> */
    public function toArray($request): array
    {
        return [
            'uuid'       => $this->resource->uuid,
            'first_name' => $this->resource->first_name,
            'last_name'  => $this->resource->last_name,
            'company'    => $this->resource->company,
            'email'      => $this->resource->email,
            'type'       => CustomerContactType::from($this->resource->type),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
