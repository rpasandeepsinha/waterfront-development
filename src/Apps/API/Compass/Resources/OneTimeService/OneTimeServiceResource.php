<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\OneTimeService;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;

/**
 * @property OneTimeService $resource
 */
class OneTimeServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var int|null $invoicesCountValue */
        $invoicesCountValue = $this->resource->getAttribute('invoices_count');
        /** @var int|null $unannouncedInvoicesCountValue */
        $unannouncedInvoicesCountValue = $this->resource->getAttribute('unannounced_invoices_count');

        $invoicesCount = $invoicesCountValue ?? 0;
        $unannouncedInvoicesCount = $unannouncedInvoicesCountValue ?? 0;

        return [
            'uuid'                => $this->resource->uuid->toString(),
            'status'              => $this->resource->status->value,
            'execution_date'      => $this->resource->execution_date->toIso8601String(),
            'amount'              => $this->resource->amount,
            'gross_price'         => $this->resource->gross_price,
            'discount_percentage' => $this->resource->discount_percentage,
            'invoiced'            => $invoicesCount > 0,
            'paid'                => $invoicesCount > 0 && $unannouncedInvoicesCount === 0,
            'customer'            => [
                'uuid'            => (string) $this->resource->customer->uuid,
                'customer_number' => $this->resource->customer->customer_number,
                'name'            => $this->resource->customer->contact_name,
            ],
            'subscription' => [
                'uuid'   => $this->resource->subscription->uuid,
                'domain' => $this->resource->subscription->domain,
            ],
            'product' => [
                'uuid' => $this->resource->product->uuid,
                'name' => $this->resource->product->name,
            ],
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
