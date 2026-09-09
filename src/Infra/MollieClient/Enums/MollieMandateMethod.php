<?php

declare(strict_types=1);

namespace Waterfront\Infra\MollieClient\Enums;

enum MollieMandateMethod: string
{
    case CREDITCARD = 'creditcard';
    case DIRECTDEBIT = 'directdebit';
    case PAYPAL = 'paypal';
}
