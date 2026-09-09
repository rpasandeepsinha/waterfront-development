<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Mailer;

use Waterfront\Domain\Mailer\MailTemplateInterface;

class MailCloudstackManagerVpsDetails implements MailTemplateInterface
{
    public function __construct(public readonly string $username, public readonly ?string $password, public readonly string $ipaddress, public readonly string $ip6address)
    {
    }

    public static function getTemplateSlug(): string
    {
        return 'cloudstack-manager-vps-details';
    }
}
