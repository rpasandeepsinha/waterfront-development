<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Enum;

use Waterfront\Domain\Products\Enums\ProductGroupType;

enum ImplementableProducts: string
{
    case DOMAIN_EXTENSION = 'domain_extensions';
    case HOSTING = 'hosting';
    case SSL = 'ssl';
    case RESELLER_DISCOUNT = 'reseller-discount';
    case MANUAL_SUBSCRIPTION = 'manual-subscription';
    case DNS = 'dns';
    case OTHER = 'other';
    case BACKUP = 'backups';
    case DOMAIN_EXPANSION = 'domein-uitbreiding';
    case REDIRECT = 'redirects';
    case MAIL_ONLY = 'mail-only';
    case VOLUME_DISCOUNT = 'volume_discounts';
    case SITEBUILDER = 'sitebuilder';
    case RESELLER_HOSTING = 'reseller-hosting';

    public static function getProductGroupTypeSlug(ImplementableProducts $implementable): ProductGroupType
    {
        return match ($implementable) {
            self::DOMAIN_EXTENSION => ProductGroupType::EXTENSION,
            self::HOSTING, self::MAIL_ONLY, self::SITEBUILDER => ProductGroupType::HOSTING,
            self::REDIRECT => ProductGroupType::REDIRECT,
            self::SSL => ProductGroupType::SSL,
            self::RESELLER_DISCOUNT => ProductGroupType::RESELLER_DISCOUNT,
            self::MANUAL_SUBSCRIPTION => ProductGroupType::MANUAL_SUBSCRIPTION,
            self::DNS => ProductGroupType::DNS,
            self::OTHER => ProductGroupType::OTHER,
            self::DOMAIN_EXPANSION => ProductGroupType::DOMAIN_EXPANSION,
            self::VOLUME_DISCOUNT => ProductGroupType::VOLUME_DISCOUNT,
            self::RESELLER_HOSTING => ProductGroupType::RESELLER_HOSTING,
            self::BACKUP => ProductGroupType::BACKUP,
        };
    }
}
