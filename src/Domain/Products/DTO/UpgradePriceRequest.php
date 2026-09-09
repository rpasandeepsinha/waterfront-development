<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO;

use Carbon\CarbonImmutable;
use Waterfront\Domain\Products\Models\Product;

class UpgradePriceRequest extends ProductPriceRequest
{
    /**
     * @param non-negative-int $amountPaid
     */
    public function __construct(
        // The product to upgrade to.
        public Product $product,
        // Used to calculate a pro rate upgrade price with respect to the end of the current subscription billing cycle.
        public readonly CarbonImmutable $endOfBillingCycle,
        // The already paid amount for the product that's been upgraded away from.
        // In other words: the amount already paid for the old product for the current billing cycle.
        public readonly int $amountPaid,
    ) {
        parent::__construct($product);
    }
}
