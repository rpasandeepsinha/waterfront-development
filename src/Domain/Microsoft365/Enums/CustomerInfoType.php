<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Enums;

enum CustomerInfoType: string
{
    case REGISTER = 'register';
    case TRANSFER = 'transfer';
}
