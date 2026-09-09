<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Customers\Models\CustomerContact;

/**
 * @mixin CustomerContact
 */
class CustomerContactResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, string>
     */
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
        ];
    }
}
