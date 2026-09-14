<?php

declare(strict_types=1);

namespace Waterfront\Domain\Puzzel\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Puzzel\Models\PuzzelCallbackTimeslot;

class PuzzelCallbackTimeslotRepository
{
    /**
     * @return Collection<int, PuzzelCallbackTimeslot>
     */
    public function all(): Collection
    {
        return PuzzelCallbackTimeslot::query()->orderBy('start_timeslot')->get();
    }

    public function getByUuid(UuidInterface $uuid): ?PuzzelCallbackTimeslot
    {
        return PuzzelCallbackTimeslot::query()->where('uuid', $uuid)->first();
    }

    public function hasAvailableCapacity(PuzzelCallbackTimeslot $timeslot): bool
    {
        return (
            $timeslot->requests()->where('desired_callback_time', '>=', CarbonImmutable::now())->count()
            < $timeslot->capacity
        );
    }
}
