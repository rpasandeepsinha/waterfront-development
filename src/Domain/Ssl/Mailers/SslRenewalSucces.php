<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Mailers;

use Waterfront\Domain\Mailer\MailTemplateInterface;

readonly class SslRenewalSucces implements MailTemplateInterface
{
    public function __construct(
        public string $domain,
        public string $expirydate
    ) {
    }

    public static function getTemplateSlug(): string
    {
        return 'ssl-certificate-successfully-delivered';
    }
}
