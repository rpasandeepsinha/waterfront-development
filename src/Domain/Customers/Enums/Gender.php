<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Enums;

enum Gender: string
{
    case MALE = 'M';
    case FEMALE = 'F';
    case NEUTRAL = 'X';
}
