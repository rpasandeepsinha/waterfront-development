<?php

declare(strict_types=1);

namespace Waterfront\Domain\Email\Actions;

use Waterfront\Domain\Email\Dto\Recipient;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Mailer\MailRecoveryCode;

class SendRecoveryCodeMail
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {
    }

    public function execute(Recipient $recipient, string $recoveryCode, string $recoveryLink): void
    {
        $this->mailer->send(
            recipients: [$recipient],
            template: new MailRecoveryCode(
                $recoveryCode,
                $recoveryLink,
            ),
        );
    }
}
