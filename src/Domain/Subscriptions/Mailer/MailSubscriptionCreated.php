<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Mailer;

use Waterfront\Domain\Mailer\MailTemplateInterface;

class MailSubscriptionCreated implements MailTemplateInterface
{
    /**
     * @param mixed[] $order
     */
    public function __construct(
        public readonly array $order,
        public readonly string $total,
        public readonly string $totalVat,
    ) {
    }

    public static function getTemplateSlug(): string
    {
        return 'subscription-created';
    }
}
