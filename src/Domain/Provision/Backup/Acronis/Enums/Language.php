<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Acronis\Enums;

/**
 * For Acronis provider.
 *
 * @see https://developer.acronis.com/doc/outbound/apis/api-library/account/language-codes.html
 */
enum Language: string
{
    case BULGARIAN = 'bg';
    case CHINESE = 'zh';
    case CHINESE_TRADITIONAL = 'zh-TW';
    case CZECH = 'cs';
    case DANISH = 'da';
    case DUTCH = 'nl';
    case ENGLISH = 'en';
    case ENGLISH_US = 'en-US';
    case FINNISH = 'fi';
    case FRENCH = 'fr';
    case GERMAN = 'de';
    case HUNGARIAN = 'hu';
    case INDONESIAN = 'id';
    case ITALIAN = 'it';
    case JAPANESE = 'ja';
    case KOREAN = 'ko';
    case MALAY = 'ms';
    case NORWEGIAN = 'nb';
    case POLISH = 'pl';
    case PORTUGUESE = 'pt';
    case PORTUGUESE_BRAZIL = 'pt-BR';
    case RUSSIAN = 'ru';
    case SERBIAN = 'sr';
    case SPANISH = 'es';
    case SPANISH_LATIN_AMERICA = 'es-419';
    case SWEDISH = 'sv';
    case TURKISH = 'tr';
}
