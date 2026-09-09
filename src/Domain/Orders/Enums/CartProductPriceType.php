<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\Enums;

enum CartProductPriceType: string
{
    case REGISTRATION = 'registration';
    case PROLONGATION = 'prolongation';
}
