<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Enums;

enum PaymentType: string
{
    /**
     * Direct payment requires a payment when ordering through the shop.
     */
    case DIRECT = 'direct';

    /**
     * Credit payment type will allow customers to pay the invoice after ordering.
     */
    case CREDIT = 'credit';
}
