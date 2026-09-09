<?php

declare(strict_types=1);

namespace Waterfront\Domain\Voucher\Enum;

enum VoucherAmountType: string
{
    case FIXED = 'fixed';
    case PERCENTAGE = 'percentage';
}
