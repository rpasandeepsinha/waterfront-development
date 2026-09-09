<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Mailer;

use Waterfront\Domain\Mailer\MailTemplateInterface;

readonly class Microsoft365SeatsChanged implements MailTemplateInterface
{
    public function __construct(
        public string $productGroupName,
        public string $productName,
        public int $old_seats,
        public int $new_seats
    ) {
    }

    public static function getTemplateSlug(): string
    {
        return 'microsoft365-seats-changed';
    }
}
