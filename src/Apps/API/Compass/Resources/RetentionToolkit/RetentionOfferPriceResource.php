<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\RetentionToolkit;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\RetentionToolkit\DTO\RetentionOfferPriceDTO;

/** @property RetentionOfferPriceDTO $resource */
class RetentionOfferPriceResource extends JsonResource
{
    /** @return array<mixed> */
    public function toArray($request): array
    {
        return [
            'eligibility' => [
                'code' => $this->resource->eligibility->code->value,
                'reason' => $this->resource->eligibility->reason,
            ],
            'grossPrice' => $this->resource->grossPrice,
            'normalNetPrice' => $this->resource->normalNetPrice,
            'offerNetPrice' => $this->resource->offerNetPrice,
            'discountAmount' => $this->resource->discountAmount,
        ];
    }
}
