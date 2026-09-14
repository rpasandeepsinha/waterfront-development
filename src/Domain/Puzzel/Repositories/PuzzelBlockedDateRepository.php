<?php

declare(strict_types=1);

namespace Waterfront\Domain\Puzzel\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Waterfront\Domain\Puzzel\Models\PuzzelBlockedDate;

class PuzzelBlockedDateRepository
{
    /**
     * @return Collection<int, PuzzelBlockedDate>
     */
    public function getTodayAndFutureDates(): Collection
    {
        return PuzzelBlockedDate::query()->whereTodayOrAfter('date')->get();
    }

    /**
     * @return LengthAwarePaginator<int, PuzzelBlockedDate>
     */
    public function getTodayAndFutureDatesPagination(int $pageSize = 100): LengthAwarePaginator
    {
        return PuzzelBlockedDate::query()->whereTodayOrAfter('date')->paginate($pageSize);
    }

    public function dateIsBlocked(CarbonImmutable $date): bool
    {
        return PuzzelBlockedDate::query()->whereDate('date', $date)->exists();
    }

    /**
     * @throws ModelNotFoundException
     */
    public function delete(int $id): void
    {
        PuzzelBlockedDate::findOrFail($id)->delete();
    }
}
