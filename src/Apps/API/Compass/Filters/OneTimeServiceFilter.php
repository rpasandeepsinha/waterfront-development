<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Waterfront\Domain\OneTimeServices\Enums\OneTimeServiceStatus;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;

class OneTimeServiceFilter
{
    public function __construct(private readonly Sorting $sorting)
    {
    }

    /**
     * @param Builder<OneTimeService> $query
     *
     * @return Builder<OneTimeService>
     */
    public function apply(Builder $query, Request $request): Builder
    {
        $this->applySearch($query, $request);
        $this->applyStatusFilter($query, $request);

        $this->sorting->apply($query, array_filter((array) $request->input('orderBy', []), 'is_string'));

        return $query;
    }

    /** @param Builder<OneTimeService> $query */
    private function applySearch(Builder $query, Request $request): void
    {
        $search = strtolower($request->string('search')->trim()->toString());

        if ($search === '' || strlen($search) > 50) {
            return;
        }

        $query->where(function (Builder $q) use ($search): void {
            $q->whereHas('subscription', function (Builder $subscription) use ($search): void {
                $subscription->where('domain', 'ilike', "%{$search}%");
            })
            ->orWhereHas('product', function (Builder $product) use ($search): void {
                $product->where('name', 'ilike', "%{$search}%")
                    ->orWhere('slug', 'ilike', "%{$search}%");
            });
        });
    }

    /** @param Builder<OneTimeService> $query */
    private function applyStatusFilter(Builder $query, Request $request): void
    {
        if (! $request->has('status')) {
            return;
        }

        $statuses = array_filter(array_map(
            static fn (mixed $status): ?OneTimeServiceStatus => is_string($status)
                ? OneTimeServiceStatus::tryFrom($status)
                : null,
            (array) $request->input('status'),
        ));

        if ($statuses === []) {
            return;
        }

        $query->whereIn('status', array_map(
            static fn (OneTimeServiceStatus $status): string => $status->value,
            $statuses,
        ));
    }
}
