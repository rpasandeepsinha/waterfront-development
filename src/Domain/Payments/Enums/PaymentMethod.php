<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Enums;

enum PaymentMethod: string
{
    case INVOICE = 'invoice';
    case MOLLIE = 'mollie';
}
