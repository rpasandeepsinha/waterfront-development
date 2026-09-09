<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Search;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Apps\API\Compass\Resources\Enum\SearchType;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * @property Subscription $resource
 */
class SearchDomainResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, int|string|null>
     */
    public function toArray($request): array
    {
        return [
            'type' => SearchType::DOMAIN->value,
            'domain' => $this->resource->domain,
            'technical_status' => $this->resource->technical_status,
            'customer_number' => $this->resource->customer->customer_number,
            'customer_name' => $this->resource->customer->contact_name,
            'subscription_id' => $this->resource->id,
        ];
    }
}
