<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Mailer;

use Waterfront\Domain\Mailer\MailTemplateInterface;

class MailCustomerSupportSubscriptionDowngrade implements MailTemplateInterface
{
    public function __construct(
        public readonly int $customerId,
        public readonly string $fromProduct,
        public readonly string $toProduct,
        public readonly string $subscriptionUuid,
        public readonly string $compassCustomerUrl,
        public readonly string $novaCustomerUrl,
        public readonly string $novaSubscriptionChangeUrl,
    ) {
    }

    public static function getTemplateSlug(): string
    {
        return 'customer-support-subscription-downgrade';
    }
}
