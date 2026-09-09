<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Products;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Customers\Models\Customer;

/**
 * Represents a customer as seen from a product group, including the discount
 * percentage that is configured for that customer on the pivot.
 *
 * @property Customer $resource
 */
class ProductGroupCustomerResource extends JsonResource
{
    /** @return array<mixed> */
    public function toArray(Request $request): array
    {
        /** @var float|string|null $discount */
        $discount = $this->resource->pivot?->getAttribute('discount');

        return [
            'uuid' => $this->resource->uuid,
            'customer_number' => $this->resource->customer_number,
            'full_name' => $this->resource->contact_name,
            'email' => $this->resource->email,
            'discount' => $discount === null ? null : (float) $discount,
        ];
    }
}
