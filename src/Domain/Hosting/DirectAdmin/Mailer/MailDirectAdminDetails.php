<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\DirectAdmin\Mailer;

use SensitiveParameter;
use Waterfront\Domain\Mailer\MailTemplateInterface;

class MailDirectAdminDetails implements MailTemplateInterface
{
    public function __construct(
        public readonly string $username,
        #[SensitiveParameter]
        public readonly string $password,
        public readonly string $domain,
        public readonly ?string $ipv4_address,
        public readonly ?bool $use_ssl,
        public readonly ?int $port,
    ) {
    }

    public static function getTemplateSlug(): string
    {
        return 'send-directadmin-details';
    }
}
