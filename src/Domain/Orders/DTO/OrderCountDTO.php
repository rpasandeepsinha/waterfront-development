<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\DTO;

readonly class OrderCountDTO
{
    public function __construct(
        public int $productId,
        public int $contractPeriod,
        public int $count,
    ) {
    }
}
