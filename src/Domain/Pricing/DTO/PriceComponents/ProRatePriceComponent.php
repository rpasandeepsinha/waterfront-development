<?php

declare(strict_types=1);

namespace Waterfront\Domain\Pricing\DTO\PriceComponents;

use Carbon\CarbonImmutable;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;

class ProRatePriceComponent extends PriceComponent
{
    /**
     * @param non-negative-int  $amountPaid
     * @param non-negative-int  $newPrice
     * @param positive-int|null $appliedOrder
     */
    public function __construct(
        public readonly CarbonImmutable $until,
        public readonly int $amountPaid = 0,
        public int $newPrice = 0,
        public ?int $appliedOrder = null,
    ) {
        parent::__construct(PriceComponentType::PRO_RATE, null, null, null, $newPrice, $appliedOrder);
    }
}
