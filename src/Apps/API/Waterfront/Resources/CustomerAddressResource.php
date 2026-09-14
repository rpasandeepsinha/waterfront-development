<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource as Resource;
use Waterfront\Domain\Customers\Models\CustomerAddress;

/**
 * @mixin CustomerAddress
 */
class CustomerAddressResource extends Resource
{
    /**
     * @param Request $request
     *
     * @return mixed[]
     */
    public function toArray($request): array
    {
        return [
            'street_name' => $this->street_name,
            'street_number' => $this->street_number,
            'street_number_addition' => $this->street_number_addition,
            'zip_code' => $this->zip_code,
            'city' => $this->city,
            'country_code' => $this->country_code,
        ];
    }
}
