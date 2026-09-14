<?php

declare(strict_types=1);

namespace Waterfront\Domain\Transfers\Mailer;

use Waterfront\Domain\Mailer\MailTemplateInterface;

class MailTransferCreatedReceiver implements MailTemplateInterface
{
    /**
     * @param string[] $domains
     */
    public function __construct(
        public readonly array $domains,
    ) {
    }

    public static function getTemplateSlug(): string
    {
        return 'send-transfer-status-created-receiver';
    }
}
