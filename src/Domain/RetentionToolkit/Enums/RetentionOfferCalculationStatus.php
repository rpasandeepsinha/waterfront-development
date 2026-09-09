<?php

declare(strict_types=1);

namespace Waterfront\Domain\RetentionToolkit\Enums;

enum RetentionOfferCalculationStatus: string
{
    case CALCULATED = 'calculated';
    case INELIGIBLE = 'ineligible';
    case MANUAL = 'manual';
    case CONFLICT = 'conflict';
}
