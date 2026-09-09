<?php

declare(strict_types=1);

namespace Waterfront\Domain\Cart\DTO;

use Waterfront\Domain\Products\Enums\UsedProductPriceType;

class AppliedPrice
{
    public function __construct(
        public int $priceInclVat,
        public int $priceExclVat,
        public UsedProductPriceType $priceType,
        public ?int $actionPeriod,
        public ?int $actionPeriodPrice,
        public ?CartVoucher $voucher,
        public ?string $priceExplanation,
    ) {
    }
}
