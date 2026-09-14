<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\RetentionToolkit;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Apps\API\Compass\Resources\Subscription\SubscriptionResource;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferItemCalculationDTO;

/** @property RetentionOfferItemCalculationDTO $resource */
class RetentionToolkitResource extends JsonResource
{
    /** @return array<mixed> */
    public function toArray($request): array
    {
        return [
            'subscription' => new SubscriptionResource($this->resource->subscription)->resolve(),
            'selectedAction' => $this->resource->selectedAction->value,
            'status' => $this->resource->status->value,
            'reason' => $this->resource->reason,
            'price' => new RetentionOfferPriceResource($this->resource->price)->resolve(),
            'effectiveDate' => $this->resource->effectiveDate,
            'oldContractStartDate' => $this->resource->oldContractStartDate,
            'oldContractEndDate' => $this->resource->oldContractEndDate,
            'newContractStartDate' => $this->resource->newContractStartDate,
            'newContractEndDate' => $this->resource->newContractEndDate,
            'cancellationDate' => $this->resource->cancellationDate,
            'creditTotal' => $this->resource->creditTotal,
            'payableAfterCredits' => $this->resource->payableAfterCredits,
            'requiresNewInvoice' => $this->resource->requiresNewInvoice,
            'replacesFutureInvoice' => $this->resource->replacesFutureInvoice,
        ];
    }
}
