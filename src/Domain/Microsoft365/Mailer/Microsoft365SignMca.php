<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Mailer;

use Waterfront\Domain\Mailer\MailTemplateInterface;

readonly class Microsoft365SignMca implements MailTemplateInterface
{
    public function __construct(
        public string $microsoft_mca_url,
    ) {
    }

    public static function getTemplateSlug(): string
    {
        return 'microsoft-mca';
    }
}
