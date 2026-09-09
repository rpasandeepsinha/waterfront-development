<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Mailer;

use Waterfront\Domain\Mailer\MailTemplateInterface;

class MailSubscriptionTechnicallyDowngraded implements MailTemplateInterface
{
    public function __construct(
        public readonly string $domain,
        public readonly string $fromProduct,
        public readonly string $toProduct,
        public readonly string $subscriptionUuid,
    ) {
    }

    public static function getTemplateSlug(): string
    {
        return 'subscription-technically-downgraded';
    }
}
