<?php

declare(strict_types=1);

namespace Waterfront\Domain\Mailer\Templates;

use Waterfront\Domain\Mailer\MailTemplateInterface;

class MailActivateNewIdentity implements MailTemplateInterface
{
    public function __construct(public readonly string $activationCode, public readonly string $activationUrl)
    {
    }

    public static function getTemplateSlug(): string
    {
        return 'activate-identity';
    }
}
