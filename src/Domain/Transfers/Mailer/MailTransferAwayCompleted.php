<?php

declare(strict_types=1);

namespace Waterfront\Domain\Transfers\Mailer;

use Waterfront\Domain\Mailer\MailTemplateInterface;

class MailTransferAwayCompleted implements MailTemplateInterface
{
    public function __construct(
        public readonly string $domainName,
    ) {
    }

    public static function getTemplateSlug(): string
    {
        return 'send-transfer-away-completed';
    }
}
