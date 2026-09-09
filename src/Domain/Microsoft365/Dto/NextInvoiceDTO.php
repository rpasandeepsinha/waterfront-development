<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Dto;

use Carbon\CarbonImmutable;

class NextInvoiceDTO
{
    public function __construct(
        public CarbonImmutable $date,
        public ?int $price,
    ) {
    }
}
