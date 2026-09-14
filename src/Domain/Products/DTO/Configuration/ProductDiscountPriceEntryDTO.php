<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO\Configuration;

readonly class ProductDiscountPriceEntryDTO
{
    public function __construct(
        public int $productId,
        public int $billingPeriod,
        public int $contractPeriod,
        public int $registrationStaffelPrice,
        public ?int $prolongationStaffelPrice,
    ) {
    }
}
