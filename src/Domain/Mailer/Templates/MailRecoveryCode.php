<?php

declare(strict_types=1);

namespace Waterfront\Domain\Mailer\Templates;

use Waterfront\Domain\Mailer\MailTemplateInterface;

class MailRecoveryCode implements MailTemplateInterface
{
    public function __construct(public readonly string $recoveryCode, public readonly string $recoveryLink)
    {
    }

    public static function getTemplateSlug(): string
    {
        return 'recovery-code';
    }
}
