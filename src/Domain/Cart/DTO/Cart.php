<?php

declare(strict_types=1);

namespace Waterfront\Domain\Cart\DTO;

use Symfony\Component\Serializer\Attribute\SerializedName;

readonly class Cart
{
    /**
     * @param array<int, CartItemWithoutPrice> $cartItems
     * @param ?array<string>                   $vouchers
     */
    public function __construct(
        #[SerializedName('paymentMethod')]
        public ?string $paymentMethod,
        #[SerializedName('items')]
        public array $cartItems,
        public ?array $vouchers,
    ) {
    }
}
