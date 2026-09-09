<?php

declare(strict_types=1);

namespace Waterfront\Apps\Webhooks\DTO\Payt;

readonly class PaytWebhookPayload
{
    public function __construct(
        public PaytWebhookEvent $event,
    ) {
    }
}
