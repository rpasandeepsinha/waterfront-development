<?php

declare(strict_types=1);

namespace Waterfront\Infra\MollieClient\Enums;

enum MollieMandateStatus: string
{
    case VALID = 'valid';
    case PENDING = 'pending';
    case INVALID = 'invalid';
}
