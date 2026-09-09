<?php

declare(strict_types=1);

namespace Waterfront\Domain\AuditLogs\DTO;

use Waterfront\Infra\Translation\TranslatorInterface;

class AuditLogUnknownParameters implements AuditLogTranslationParameters
{
    /**
     * @return array<string, string>
     */
    public function getParameters(TranslatorInterface $translator): array
    {
        return [];
    }
}
