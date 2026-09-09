<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Enums\Tenants;

enum ExternalOperationStatus: string
{
    case NO_OPERATION = 'no_operation';
    case DELETING = 'deleting';
    case RECOVERING = 'recovering';
}
