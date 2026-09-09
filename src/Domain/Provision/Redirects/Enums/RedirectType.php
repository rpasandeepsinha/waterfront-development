<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Enums;

enum RedirectType: string
{
    case PERMANENT = '301';
    case TEMPORARY = '302';
    case FRAME = 'frame';
}
