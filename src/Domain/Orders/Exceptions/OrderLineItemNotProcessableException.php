<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\Exceptions;

use RuntimeException;

class OrderLineItemNotProcessableException extends RuntimeException
{
    private function __construct(public readonly string $translationKey)
    {
        parent::__construct($translationKey);
    }

    public static function alreadyProcessed(): self
    {
        return new self('nova-action.process_order_line_item.already_processed');
    }

    public static function invalidOrderStatus(): self
    {
        return new self('nova-action.process_order_line_item.invalid_status');
    }

    public static function parentSubscriptionNotFound(): self
    {
        return new self('nova-action.error.parent_subscription_not_exists');
    }
}
