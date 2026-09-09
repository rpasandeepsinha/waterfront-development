<?php

declare(strict_types=1);

namespace Waterfront\Domain\Invoices\DTO;

readonly class AdministrationFees
{
    public function __construct(
        public int $productId,
        public int $price,
        public string $name,
    ) {
    }
}
