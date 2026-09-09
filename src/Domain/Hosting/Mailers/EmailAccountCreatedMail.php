<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Mailers;

use Waterfront\Domain\Mailer\MailTemplateInterface;

readonly class EmailAccountCreatedMail implements MailTemplateInterface
{
    public function __construct(
        public string $email_created,
    ) {
    }

    public static function getTemplateSlug(): string
    {
        return 'email-account-created';
    }
}
