<?php

declare(strict_types=1);

namespace Waterfront\Domain\Email\Enums;

enum ReceiverType: string
{
    case CUSTOMER_CONTACT = 'customerContact';
    case CUSTOMER = 'customer';
    case IDENTITY = 'identity';
}
