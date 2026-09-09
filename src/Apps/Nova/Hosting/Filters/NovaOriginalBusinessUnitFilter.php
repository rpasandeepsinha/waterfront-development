<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Servers\Models\LegacyRedirectingServer;

class NovaOriginalBusinessUnitFilter extends Filter
{
    public function name(): string
    {
        return 'Original business unit';
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('original_business_unit', $value);
    }

    /**
     * @return array<mixed>
     */
    public function options(NovaRequest $request): array
    {
        return LegacyRedirectingServer::query()
            ->groupBy('original_business_unit')
            ->orderByRaw('LOWER(original_business_unit)')
            ->pluck('original_business_unit')
            ->toArray();
    }
}
