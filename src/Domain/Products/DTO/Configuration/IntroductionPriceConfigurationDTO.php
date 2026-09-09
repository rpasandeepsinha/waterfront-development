<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO\Configuration;

readonly class IntroductionPriceConfigurationDTO
{
    public function __construct(
        public int $contractPeriod,
        public ?int $maxUsesPerCustomer,
        public ?int $firstMonthsDiscountPeriod,
    ) {
    }
}
