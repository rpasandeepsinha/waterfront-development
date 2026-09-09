<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO;

use Illuminate\Support\Collection;
use Waterfront\Domain\Cart\DTO\VoucherInformation;

class TotalCollectionPrice
{
    /**
     * @param Collection<int, ProductWithCalculatedPrice> $items
     * @param array<string, VoucherInformation>           $vouchers
     * @param int<0,max>                                  $totalExclVatPrice
     * @param int<0,max>                                  $totalInclVatPrice
     */
    public function __construct(
        public readonly Collection $items,
        public array $vouchers,
        public int $totalExclVatPrice,
        public int $totalInclVatPrice,
    ) {
    }
}
