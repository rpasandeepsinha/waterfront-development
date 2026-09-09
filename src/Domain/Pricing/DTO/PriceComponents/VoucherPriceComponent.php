<?php

declare(strict_types=1);

namespace Waterfront\Domain\Pricing\DTO\PriceComponents;

use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Voucher\Models\Voucher;

class VoucherPriceComponent extends PriceComponent
{
    /**
     * @param non-negative-int|null $fixedDiscount
     * @param non-negative-int      $newPrice
     * @param non-negative-int      $appliedAmount
     * @param positive-int|null     $appliedOrder
     */
    public function __construct(
        public ?int $fixedDiscount,
        public ?float $percentageDiscount,
        public int $newPrice,
        public int $appliedAmount,
        public Voucher $voucher,
        public ?int $appliedOrder = null,
    ) {
        assert($percentageDiscount === null || ($percentageDiscount >= 0.0 && $percentageDiscount <= 100.0));
        assert($fixedDiscount !== null || $percentageDiscount !== null);

        parent::__construct(PriceComponentType::VOUCHER, $fixedDiscount, $percentageDiscount, null, $newPrice, $appliedOrder);
    }
}
