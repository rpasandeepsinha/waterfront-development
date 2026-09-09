<?php

declare(strict_types=1);

namespace Waterfront\Domain\Puzzel\Dto;

use Illuminate\Support\Collection;
use Waterfront\Domain\Puzzel\Models\PuzzelCallbackRequest;
use Waterfront\Domain\Puzzel\Models\PuzzelCallbackTimeslot;
use Waterfront\Infra\PuzzelClient\DTO\RequestInQueue;

class SupportCallSchedule
{
    /**
     * @param array<string, Collection<int, PuzzelCallbackTimeslot>> $slotsByDate
     */
    public function __construct(
        public readonly PuzzelCallbackRequest|RequestInQueue|null $existingRequest,
        public readonly array $slotsByDate,
    ) {
    }
}
