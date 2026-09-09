<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Subscription\Cancellation;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Invoices\Models\Invoice;

/**
 * @property Invoice $resource
 */
class CreditableInvoiceLineResource extends JsonResource
{
    /** @return array<mixed> */
    public function toArray($request): array
    {
        return [
            'subscription_id' => $this->resource->subscription?->id,
            'invoice_line_id' => $this->resource->id,
            'start_date' => $this->resource->start_date->toW3cString(),
            'end_date' => $this->resource->end_date->toW3cString(),
            'net_price' => $this->resource->net_price,
        ];
    }
}
