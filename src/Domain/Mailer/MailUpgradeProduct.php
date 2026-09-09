<?php

declare(strict_types=1);

namespace Waterfront\Domain\Mailer;

class MailUpgradeProduct implements MailTemplateInterface
{
    public function __construct(
        public readonly string $productName,
        public readonly string $domainName,
        public readonly int $contractPeriod,
        public readonly int $grossPrice
    ) {
    }

    public static function getTemplateSlug(): string
    {
        return 'upgrade-product';
    }
}
