<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Enums;

enum SubscriptionCategory: string
{
    case TECHNICAL = 'technical';
    case PAYMENT = 'payment';
    case CUSTOMER_ACTION = 'customer_action';
    case PRODUCT_CHANGE = 'product_change';
    case SUSPENSION = 'suspension';
    case UNSUSPENSION = 'unsuspension';
}
