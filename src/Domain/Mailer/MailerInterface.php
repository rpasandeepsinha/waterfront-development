<?php

declare(strict_types=1);

namespace Waterfront\Domain\Mailer;

interface MailerInterface
{
    /**
     * @param array<IsMailable> $recipients
     * @param array<IsMailable> $cc
     */
    public function send(
        array $recipients,
        MailTemplateInterface $template,
        array $cc = []
    ): void;
}
