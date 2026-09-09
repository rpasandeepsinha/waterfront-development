<?php

declare(strict_types=1);

namespace Waterfront\Domain\Translations\Enums;

enum TranslationPlatforms: string
{
    case AUDIT_LOG_SUMMARY = 'audit-log-summary';
    case ATLANTIS = 'atlantis';
    case BEACON = 'beacon';
    case COAST = 'coast';
    case COMPASS = 'compass';
    case WATERFRONT_BACKEND = 'waterfront-backend';
}
