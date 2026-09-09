<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Enums;

enum TransferTypeType: string
{
    case OUT = 'OUT';
    case OUT_INTERNAL = 'OUT_INTERNAL';
    case IN = 'IN';
    case IN_INTERNAL = 'IN_INTERNAL';
}
