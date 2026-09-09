<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Enums;

enum ProductType: string
{
    case FREE_REDIRECT = 'free-redirect';
    case REDIRECT = 'redirect';
    case FREE_DNS = 'free-dns';
    case PREMIUM_DNS = 'premium-dns';
    case BASIC_DNS = 'basic-dns';
    case PARKING_DNS = 'parking-dns';
    case SITEBUILDER = 'sitebuilder';
    case MAIL_ONLY = 'mail_only';
    case ADMINISTRATION_FEES = 'administration-fees';
    case EMAIL_START = 'email-start';
    case EMAIL_MAX = 'email-max';

    /**
     * @return ProductType[]
     */
    public static function getRedirectProductTypes(): array
    {
        return [
            self::FREE_REDIRECT,
            self::REDIRECT,
        ];
    }

    /**
     * @return ProductType[]
     */
    public static function getDnsProductTypes(): array
    {
        return [
            self::FREE_DNS,
            self::PARKING_DNS,
            self::BASIC_DNS,
            self::PREMIUM_DNS,
        ];
    }
}
