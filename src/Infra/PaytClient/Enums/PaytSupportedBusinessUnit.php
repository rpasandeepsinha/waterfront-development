<?php

declare(strict_types=1);

namespace Waterfront\Infra\PaytClient\Enums;

enum PaytSupportedBusinessUnit: string
{
    case DEHEEG = 'de-heeg';
    case NEOSTRADA = 'neostrada';
    case REALHOSTING = 'realhosting';
    case SOHOSTED = 'sohosted';
    case VERSIO2 = 'versio-2';
    case YOURHOSTING2 = 'yourhosting-2';
}
