<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Customers\Models\MigratedCustomer;

/**
 * @property MigratedCustomer $resource
 */
class CustomerMigrationResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'reference_customer_number' => $this->resource->reference_customer_number,
            'reference_name' => $this->resource->reference_name,
            'group_type' => $this->resource->group_type,
            'successful' => $this->resource->successful,
            'administrative_successful' => $this->resource->administrative_successful,
            'technical_successful' => $this->resource->technical_successful,
            'billing_successful' => $this->resource->billing_successful,
            'dns_successful' => $this->resource->dns_successful,
            'enable_invoicing' => $this->resource->enable_invoicing,
            'migrated_at' => $this->resource->migrated_at,
        ];
    }
}
