<?php

declare(strict_types=1);

namespace Waterfront\Support\Enums;

enum Environment: string
{
    case DEV = 'dev';
    case PROD = 'prod';
    case SIT = 'sit';
    case TST = 'tst';
    case UAT = 'uat';
}
