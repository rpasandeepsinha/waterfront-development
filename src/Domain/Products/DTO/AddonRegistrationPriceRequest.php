<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO;

use Carbon\CarbonImmutable;
use Waterfront\Domain\Products\Models\Product;

class AddonRegistrationPriceRequest extends ProductPriceRequest
{
    public function __construct(
        public Product $product,
        // Used to calculate a pro rate addon price with respect to the parent subscription.
        public readonly CarbonImmutable $endOfBillingCycle,
    ) {
        parent::__construct($product);
    }
}
