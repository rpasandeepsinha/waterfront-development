<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\DTO;

use Carbon\CarbonImmutable;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;

readonly class RenewalInfoDTO
{
    /**
     * @param int<0,max> $grossPrice
     * @param int<0,max> $netPrice
     */
    public function __construct(
        public Product $product,
        public int $contractPeriod,
        public int $billingPeriod,
        public int $grossPrice,
        public int $netPrice,
        public CarbonImmutable $startDate,
        public CarbonImmutable $endDate,
        public ?SubscriptionMutation $appliedSubscriptionMutation = null,
        public ?Price $price = null,
    ) {
    }
}
