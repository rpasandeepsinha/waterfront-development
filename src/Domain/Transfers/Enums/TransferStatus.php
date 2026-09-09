<?php

declare(strict_types=1);

namespace Waterfront\Domain\Transfers\Enums;

enum TransferStatus: string
{
    case REQUESTED = 'requested';
    case STARTED = 'started';
    case CANCELED = 'canceled';
    case REJECTED = 'rejected';
    case ACCEPTED = 'accepted';
    case FAILED = 'failed';
    case COMPLETED = 'completed';
}
