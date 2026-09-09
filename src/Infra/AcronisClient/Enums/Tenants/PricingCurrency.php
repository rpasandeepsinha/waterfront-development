<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Enums\Tenants;

enum PricingCurrency: string
{
    case AUD = 'AUD';
    case BRL = 'BRL';
    case CAD = 'CAD';
    case EUR = 'EUR';
    case CNY = 'CNY';
    case GBP = 'GBP';
    case JPY = 'JPY';
    case KZT = 'KZT';
    case NZD = 'NZD';
    case RUB = 'RUB';
    case USD = 'USD';
    case ZAR = 'ZAR';
}
