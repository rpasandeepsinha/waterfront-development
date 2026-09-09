<?php

declare(strict_types=1);

namespace Waterfront\Domain\Email\Actions;

use Waterfront\Domain\Email\Dto\Recipient;
use Waterfront\Domain\Mailer\MailActivateAccount;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Mailer\Templates\MailActivateNewIdentity;

class SendActivationMail
{
    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function execute(
        Recipient $recipient,
        MailActivateAccount|MailActivateNewIdentity $template
    ): void {
        $this->mailer->send(
            recipients: [$recipient],
            template: $template
        );
    }
}
