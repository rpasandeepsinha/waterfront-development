<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Enums;

enum Locale: string
{
    case DUTCH = 'nl-NL';
    case ENGLISH = 'en-US';
    case GERMAN = 'de-DE';
    case SPANISH = 'es-ES';
    case FRENCH = 'fr-FR';
}
