<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Search;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Apps\API\Compass\Resources\Enum\SearchType;
use Waterfront\Domain\Customers\Models\Customer;

/**
 * @property Customer $resource
 */
class SearchMigrationCustomerResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, int|string|null>
     */
    public function toArray($request): array
    {
        return [
            'type' => SearchType::CUSTOMER->value,
            'customer_number' => $this->resource->customer_number,
            'first_name' => $this->resource->first_name,
            'last_name' => $this->resource->last_name,
            'organization' => $this->resource->organization ?? null,
        ];
    }
}
