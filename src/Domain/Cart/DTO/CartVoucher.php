<?php

declare(strict_types=1);

namespace Waterfront\Domain\Cart\DTO;

use Waterfront\Domain\Voucher\Enum\VoucherAmountType;

class CartVoucher
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public string $description,
        public int $amount,
        public VoucherAmountType $type,
        public bool $valid,
        public int $appliedAmount,
    ) {
    }
}
