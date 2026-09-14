<?php

declare(strict_types=1);

namespace Waterfront\Domain\Mailer;

class MailDowngradeProduct implements MailTemplateInterface
{
    public function __construct(
        public readonly string $domainName,
        public readonly string $productName,
        public readonly int $contractPeriod,
        public readonly int $grossPrice,
    ) {
    }

    public static function getTemplateSlug(): string
    {
        return 'downgrade-product';
    }
}
