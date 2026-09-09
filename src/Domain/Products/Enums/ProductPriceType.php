<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Enums;

enum ProductPriceType: string
{
    case PROLONGATION = 'prolongation';
    case REGISTRATION = 'registration';
}
