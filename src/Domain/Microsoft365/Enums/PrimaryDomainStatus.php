<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Enums;

enum PrimaryDomainStatus: string
{
    case VERIFICATION_PENDING = 'verification_pending';
    case VERIFIED = 'verified';
    case VERIFICATION_FAILED = 'verification_failed';
    case ACTIVE = 'active';

    /**
     * @return array<int, PrimaryDomainStatus|null>
     */
    public static function allowedToChangeDomainStatus(): array
    {
        return [
            null,
            self::ACTIVE,
            self::VERIFICATION_FAILED,
        ];
    }
}
