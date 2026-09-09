<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Enums;

enum RevisionType: string
{
    case ADD = 'ADD';
    case MOD = 'MOD';
    case DEL = 'DEL';
}
