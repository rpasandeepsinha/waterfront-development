<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\DTO;

readonly class VatDTO
{
    public function __construct(
        public string $vatCode,
        public float $vatRate,
    ) {
    }
}
