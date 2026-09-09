<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Mailers;

use Waterfront\Domain\Mailer\MailTemplateInterface;

class MailDomainCreationFailed implements MailTemplateInterface
{
    public function __construct(
        public readonly string $domain,
        public readonly string $reason
    ) {
    }

    public static function getTemplateSlug(): string
    {
        return 'domain-creation-failed';
    }
}
