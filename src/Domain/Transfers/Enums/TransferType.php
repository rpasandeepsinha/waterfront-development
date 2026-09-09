<?php

declare(strict_types=1);

namespace Waterfront\Domain\Transfers\Enums;

enum TransferType: string
{
    case INCOMING = 'INCOMING';
    case OUTGOING = 'OUTGOING';
}
