<?php

declare(strict_types=1);

namespace Waterfront\Domain\RetentionToolkit\DTO;

readonly class RetentionOfferPriceDTO
{
    /**
     * @param non-negative-int|null $grossPrice
     * @param non-negative-int|null $normalNetPrice
     * @param non-negative-int|null $offerNetPrice
     * @param non-negative-int|null $discountAmount
     */
    public function __construct(
        public RetentionOfferEligibilityResultDTO $eligibility,
        public ?int $grossPrice,
        public ?int $normalNetPrice,
        public ?int $offerNetPrice,
        public ?int $discountAmount,
    ) {
    }
}
