<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\OneTimeService;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;

/**
 * @property OneTimeService $resource
 */
class ListOneTimeServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var int|null $invoicesCount */
        $invoicesCount = $this->resource->getAttribute('invoices_count');

        return [
            'uuid' => (string) $this->resource->uuid,
            'status' => $this->resource->status->value,
            'execution_date' => $this->resource->execution_date->toIso8601String(),
            'amount' => $this->resource->amount,
            'invoiced' => ($invoicesCount ?? 0) > 0,
            'customer' => [
                'uuid' => (string) $this->resource->customer->uuid,
                'customer_number' => $this->resource->customer->customer_number,
                'name' => $this->resource->customer->contact_name,
            ],
            'subscription' => [
                'uuid' => $this->resource->subscription->uuid,
                'domain' => $this->resource->subscription->domain,
            ],
            'product' => [
                'uuid' => $this->resource->product->uuid,
                'name' => $this->resource->product->name,
            ],
        ];
    }
}
