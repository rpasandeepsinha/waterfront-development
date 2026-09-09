<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Customers\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Webmozart\Assert\Assert;

class MigratedBatchFilter extends Filter
{
    private const string FIELD_GROUP_TYPE = 'group_type';

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where(self::FIELD_GROUP_TYPE, $value);
    }

    /**
     * @return array<mixed>
     */
    public function options(NovaRequest $request): array
    {
        $options = MigratedCustomer::query()
            ->groupBy(self::FIELD_GROUP_TYPE)
            ->pluck(self::FIELD_GROUP_TYPE)
            ->toArray();
        Assert::allString($options);

        natcasesort($options);

        return array_reverse($options);
    }
}
