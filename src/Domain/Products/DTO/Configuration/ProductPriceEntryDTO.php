<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO\Configuration;

readonly class ProductPriceEntryDTO
{
    /**
     * @param AdditionalPriceDTO[]|null $additionalPrices
     */
    public function __construct(
        public int $billingPeriod,
        public int $contractPeriod,
        public int $registrationPrice,
        public ?array $additionalPrices,
    ) {
    }
}
