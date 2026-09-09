<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\Enums;

enum OrderLineItemStatus: string
{
    case PROLONGATION = 'prolongation';
    case REGISTRATION = 'registration';

    // No longer in use for new orders, but must be kept in order to view existing orders.
    case ONE_TIME_SERVICE = 'one_time_service';
    case TRANSFER = 'transfer';
    case CONDITIONAL = 'conditional';
}
