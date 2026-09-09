<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Invoice;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Invoices\Models\Invoice;

/**
 * @property Invoice $resource
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, CarbonImmutable|bool|float|int|string|null>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'type' => $this->resource->type,
            'groupLabel' => $this->resource->group_label,
            'startDate' => $this->resource->start_date,
            'endDate' => $this->resource->end_date,
            'period' => $this->resource->period,
            'grossPrice' => $this->resource->gross_price,
            'netPrice' => $this->resource->net_price,
            'vatCode' => $this->resource->vat_code,
            'vatRate' => $this->resource->vat_rate,
            'subscriptionId' => $this->resource->subscription_id,
            'sentToHarborAt' => $this->resource->sent_to_harbor_at,
            'createdAt' => $this->resource->created_at,
        ];
    }
}
