<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Mailer;

use Waterfront\Domain\Mailer\MailTemplateInterface;

class MailSubscriptionCancelled implements MailTemplateInterface
{
    public function __construct(
        public readonly string $productGroupName,
        public readonly string $productName,
        public readonly string $domainName,
        public readonly string $subscriptionEndDate,
        public readonly string $cancelOption,
    ) {
    }

    public static function getTemplateSlug(): string
    {
        return 'subscription_canceled';
    }
}
