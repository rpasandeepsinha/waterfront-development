<?php

declare(strict_types=1);

namespace Waterfront\Domain\Mailer;

interface MailTemplateInterface
{
    public static function getTemplateSlug(): string;
}
