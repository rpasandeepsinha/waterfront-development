<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Enums\OfferingItems;

enum MeasurementUnit: string
{
    case BYTES = 'bytes';
    case QUANTITY = 'quantity';
    case SECONDS = 'seconds';
    case NA = 'n/a';
}
