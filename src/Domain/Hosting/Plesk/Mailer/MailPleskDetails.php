<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Plesk\Mailer;

use Waterfront\Domain\Mailer\MailTemplateInterface;

class MailPleskDetails implements MailTemplateInterface
{
    public function __construct(
        public readonly string $username,
        public readonly string $domain,
        public readonly ?string $ipv4_address,
        public readonly string $password,
    ) {
    }

    public static function getTemplateSlug(): string
    {
        return 'send-plesk-details';
    }
}
