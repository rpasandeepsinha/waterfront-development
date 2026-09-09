<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\DTO;

readonly class CartOrder
{
    /**
     * @param array<string> $vouchers
     */
    public function __construct(
        public string $paymentMethod,
        public CartOrderSubscription $subscriptions,
        public ?array $vouchers,
    ) {
    }
}
