<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Subscription\Cancellation;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Apps\API\Compass\DTO\CancellationPreviewDTO;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCrediter\InvoiceToCredit;
use Waterfront\Infra\Common\DateTimeFormat;

/** @property CancellationPreviewDTO $resource */
class CancellationPreviewResource extends JsonResource
{
    /** @return array<mixed> */
    public function toArray($request): array
    {
        return [
            'subscriptions' => AffectedSubscriptionResource::collection($this->resource->subscriptions),
            'blocking_problems' => CancellationProblemResource::collection($this->resource->blockingProblems),
            'credit_allowed' => $this->resource->creditAllowed,
            'credit_applied' => $this->resource->creditApplied,
            'max_selectable_end_date' => $this->resource->maxSelectableEndDate->format(DateTimeFormat::DATE),
            'creditable_invoice_lines' => CreditableInvoiceLineResource::collection($this->resource->creditableInvoiceLines),
            'credit_invoice_lines' => CreditInvoiceLineResource::collection($this->resource->creditInvoiceLines),
            'credit_total' => array_reduce(
                $this->resource->creditInvoiceLines,
                fn (int $total, InvoiceToCredit $invoiceToCredit): int => $total - $invoiceToCredit->getAmountToCredit(),
                0
            ),
        ];
    }
}
