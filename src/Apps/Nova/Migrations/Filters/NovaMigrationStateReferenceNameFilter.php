<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Migrations\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Customers\Models\MigratedCustomer;

class NovaMigrationStateReferenceNameFilter extends Filter
{
    public function name(): string
    {
        return 'Reference label';
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->whereRelation(
            'migratedSubscriptions.migratedCustomers',
            'reference_name',
            $value,
        );
    }

    /**
     * @return array<mixed>
     */
    public function options(NovaRequest $request): array
    {
        return MigratedCustomer::query()
            ->groupBy('reference_name')
            ->orderByRaw('LOWER(reference_name)')
            ->pluck('reference_name')
            ->toArray();
    }
}
