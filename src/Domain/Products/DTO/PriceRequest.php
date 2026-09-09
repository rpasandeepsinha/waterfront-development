<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO;

use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Voucher\Models\Voucher;

class PriceRequest
{
    /**
     * @param ProductPriceRequest[] $productPriceRequests
     * @param Voucher[]             $vouchers
     */
    public function __construct(
        public array $productPriceRequests,
        public ?Customer $customer,
        public array $vouchers = [],
        public bool $requestingForOrder = false,
    ) {
    }
}
