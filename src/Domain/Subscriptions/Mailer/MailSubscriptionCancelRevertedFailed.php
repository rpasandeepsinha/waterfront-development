<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Mailer;

use Waterfront\Domain\Mailer\MailTemplateInterface;

class MailSubscriptionCancelRevertedFailed implements MailTemplateInterface
{
    public function __construct(
        public readonly string $domainName,
        public readonly string $productDescription,
        public readonly string $subscriptionEndDate,
        public readonly int $contractPeriod,
    ) {
    }

    public static function getTemplateSlug(): string
    {
        return 'subscription-cancel-reverted-failed';
    }
}
