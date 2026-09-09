<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Mailer;

use Waterfront\Domain\Mailer\MailTemplateInterface;

readonly class Microsoft365PrimaryDomainUpdated implements MailTemplateInterface
{
    public function __construct(
        public string $primary_domain
    ) {
    }

    public static function getTemplateSlug(): string
    {
        return 'microsoft365-primary-domain-updated';
    }
}
