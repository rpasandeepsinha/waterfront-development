<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Enums;

enum RtrResponseSource: string
{
    case API_CALL = 'api';
    case NOTIFICATION = 'notification';
}
