<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\Enums;

enum PersonalType: string
{
    case Undefined = 'Undefined';
    case Auto = 'Auto';
    case Pick = 'Pick';
}
