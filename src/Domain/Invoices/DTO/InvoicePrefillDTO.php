<?php

declare(strict_types=1);

namespace Waterfront\Domain\Invoices\DTO;

use Carbon\CarbonImmutable;
use Waterfront\Domain\Products\Models\Product;

readonly class InvoicePrefillDTO
{
    public function __construct(
        public ?CarbonImmutable $startDate,
        public ?CarbonImmutable $endDate,
        public ?int $grossPrice,
        public ?int $netPrice,
        public Product $product,
    ) {
    }
}
