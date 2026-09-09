<?php

declare(strict_types=1);

namespace Waterfront\Domain\Cart\DTO;

use Symfony\Component\Serializer\Attribute\SerializedName;

readonly class CartWithPrices
{
    /**
     * @param array<int, CartItemWithPrice>      $cartItems
     * @param ?array<string, VoucherInformation> $vouchers
     */
    public function __construct(
        #[SerializedName('items')]
        public array $cartItems,
        public int $totalExclVatPrice,
        public int $totalInclVatPrice,
        public int $administrationFee,
        public ?array $vouchers,
    ) {
    }
}
