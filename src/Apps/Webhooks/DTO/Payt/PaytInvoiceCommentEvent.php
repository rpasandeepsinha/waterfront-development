<?php

declare(strict_types=1);

namespace Waterfront\Apps\Webhooks\DTO\Payt;

class PaytInvoiceCommentEvent extends PaytWebhookEvent
{
    public function __construct(
        string $eventName,
        string $eventTime,
        public readonly PaytWebhookContext $context,
    ) {
        parent::__construct($eventName, $eventTime);
    }
}
