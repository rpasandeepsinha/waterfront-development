<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\DTO;

use Carbon\CarbonImmutable;
use Waterfront\Domain\Pricing\Models\SubscriptionPrice;
use Waterfront\Domain\Products\Models\Product;

readonly class NextInvoicePriceDTO
{
    /**
     * @param int<0,max> $grossPrice
     * @param int<0,max> $netPrice
     */
    public function __construct(
        public Product $product,
        public int $billingPeriod,
        public int $grossPrice,
        public int $netPrice,
        public ?SubscriptionPrice $nextPrice,
        public CarbonImmutable $startDate,
        public CarbonImmutable $endDate,
    ) {
    }
}
