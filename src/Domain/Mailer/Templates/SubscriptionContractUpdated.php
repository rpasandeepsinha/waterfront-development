<?php

declare(strict_types=1);

namespace Waterfront\Domain\Mailer\Templates;

use Waterfront\Domain\Mailer\MailTemplateInterface;

class SubscriptionContractUpdated implements MailTemplateInterface
{
    public function __construct(
        public readonly int $contract_period,
        public readonly int $billing_period,
        public readonly string $domain,
    ) {
    }

    public static function getTemplateSlug(): string
    {
        return 'subscription-contract-updated';
    }
}
