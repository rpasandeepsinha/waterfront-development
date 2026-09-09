<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Enums;

enum ProvisionStatus: string
{
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case VALIDATION_ERROR = 'validation_error';
    case RETRYING = 'retrying';
    case DELETED = 'deleted';
    case DELETING = 'deleting';
    case DELETION_FAILED = 'deletion_failed';
    case PENDING = 'pending';

    /** @return self[] */
    public static function getFailedStatuses(): array
    {
        return [
            self::FAILED,
            self::VALIDATION_ERROR,
            self::DELETION_FAILED,
        ];
    }
}
