<?php

declare(strict_types=1);

namespace Waterfront\Domain\Sitebuilder\Mailer;

use Waterfront\Domain\Mailer\MailTemplateInterface;

class MailSitebuilderActivation implements MailTemplateInterface
{
    public function __construct(
        public readonly string $domainName,
    ) {
    }

    public static function getTemplateSlug(): string
    {
        return 'send-sitebuilder-activation';
    }
}
