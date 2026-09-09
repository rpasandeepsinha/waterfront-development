<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Enums;

enum PaymentStatus: string
{
    case OPEN = 'open';
    case CANCELED = 'canceled';
    case PENDING = 'pending';
    case AUTHORIZED = 'authorized';
    case EXPIRED = 'expired';
    case FAILED = 'failed';
    case PAID = 'paid';
    case NEEDS_PAYMENT = 'needsPayment';
    case OK = 'ok';
}
