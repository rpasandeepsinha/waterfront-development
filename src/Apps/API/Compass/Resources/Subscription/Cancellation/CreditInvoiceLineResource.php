<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Subscription\Cancellation;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCrediter\InvoiceToCredit;

/**
 * @property InvoiceToCredit $resource
 */
class CreditInvoiceLineResource extends JsonResource
{
    /** @return array<mixed> */
    public function toArray($request): array
    {
        $invoiceLine = $this->resource->getInvoice();

        return [
            'subscription_id' => $invoiceLine->subscription?->id,
            'invoice_line_id' => $invoiceLine->id,
            'start_date' => $this->resource->getCreditStartDate()->toW3cString(),
            'end_date' => $invoiceLine->end_date->toW3cString(),
            'net_price' => -1 * $this->resource->getAmountToCredit(),
        ];
    }
}
