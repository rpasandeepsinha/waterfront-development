<?php

declare(strict_types=1);

namespace Waterfront\Domain\Puzzel\Dto;

class SupportCallTimeslotUsage
{
    public function __construct(
        public readonly string $date,
        public readonly string $timeslotUuid,
        public readonly int $requestsCount,
    ) {
    }
}
