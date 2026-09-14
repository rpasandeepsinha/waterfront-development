<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Mailers;

use Waterfront\Domain\Mailer\MailTemplateInterface;

class PremiumDomainPriceRequested implements MailTemplateInterface
{
    public function __construct(
        public readonly string $domain_name,
        public readonly string $customer_email_address,
    ) {
    }

    public static function getTemplateSlug(): string
    {
        return 'premium-domain-price-requested';
    }
}
