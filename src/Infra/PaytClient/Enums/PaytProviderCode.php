<?php

declare(strict_types=1);

namespace Waterfront\Infra\PaytClient\Enums;

enum PaytProviderCode: string
{
    case MOLLIE = 'mollie';
    case TWIKEY = 'twikey';
}
