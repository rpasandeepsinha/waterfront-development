<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO;

use Waterfront\Domain\Products\Models\Product;
use Webmozart\Assert\Assert;

class RegistrationPriceRequest extends ProductPriceRequest
{
    public function __construct(Product $product, int $quantity = 1, ?int $contractPeriod = null, ?int $billingPeriod = null, ?string $experimentSlug = null)
    {
        // When requesting more than 1 item we need to know for which period.
        if ($quantity > 1) {
            Assert::notNull($contractPeriod);
            Assert::notNull($billingPeriod);
        }

        parent::__construct($product, $quantity, $contractPeriod, $billingPeriod, $experimentSlug);
    }
}
