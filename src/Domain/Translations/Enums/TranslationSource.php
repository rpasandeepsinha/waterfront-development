<?php

declare(strict_types=1);

namespace Waterfront\Domain\Translations\Enums;

enum TranslationSource: string
{
    case ATLANTIS = 'atlantis';
    case WATERFRONT = 'waterfront-backend';
    case COMPASS = 'compass';
    case BEACON = 'beacon';
    case COAST = 'coast';
}
