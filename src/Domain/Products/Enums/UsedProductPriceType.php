<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Enums;

enum UsedProductPriceType: string
{
    case REGULAR_PRICE = 'regular';
    case VOUCHER_PRICE = 'voucher';
}
