<?php

declare(strict_types=1);

namespace Waterfront\Domain\Experiment\Enums;

enum ExperimentType: string
{
    case PRICING_LADDER = 'pricing-ladder';
}
