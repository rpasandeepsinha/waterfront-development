<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Mailer;

use Waterfront\Domain\Mailer\MailTemplateInterface;

class MailSubscriptionUnSuspendedDetails implements MailTemplateInterface
{
    public function __construct(
        public readonly string $domain,
    ) {
    }

    public static function getTemplateSlug(): string
    {
        return 'subscription-unsuspended';
    }
}
