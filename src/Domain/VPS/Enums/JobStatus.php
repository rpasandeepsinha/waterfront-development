<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Enums;

/**
 * This Enum represents the status of a job from a Cloudstack API response.
 */
enum JobStatus: int
{
    case PENDING = 0;
    case SUCCESS = 1;
    case FAILED = 2;
}
