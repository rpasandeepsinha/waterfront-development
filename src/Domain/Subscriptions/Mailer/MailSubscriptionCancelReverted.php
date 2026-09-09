<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Mailer;

use Waterfront\Domain\Mailer\MailTemplateInterface;

class MailSubscriptionCancelReverted implements MailTemplateInterface
{
    /**
     * @param array<array{domainName: string, productDescription: string, productName: string, subscriptionEndDate: string, contractPeriod: int}> $subscriptions
     */
    public function __construct(public readonly array $subscriptions)
    {
    }

    public static function getTemplateSlug(): string
    {
        return 'subscription-cancel-reverted';
    }
}
