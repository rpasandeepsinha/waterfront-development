<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\General\Filters;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\DateFilter;
use Laravel\Nova\Http\Requests\NovaRequest;

class NovaSpecificDateFilter extends DateFilter
{
    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        assert(is_string($value));
        $startOfDay = CarbonImmutable::parse($value)->startOfDay();
        $endOfDay = CarbonImmutable::parse($value)->endOfDay();

        return $query->whereBetween('created_at', [$startOfDay, $endOfDay]);
    }
}
