<?php

declare(strict_types=1);

namespace Waterfront\Domain\Mailer;

class CustomerEmailUpdateEmail implements MailTemplateInterface
{
    public function __construct(public readonly string $newCustomerEmail)
    {
    }

    public static function getTemplateSlug(): string
    {
        return 'customer-email-updated';
    }
}
