<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Migrations\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class NovaMigrationStateBatchGroupFilter extends Filter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-filter.migration_state.batch_group');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->whereRelation(
            'migratedSubscriptions.migratedCustomers',
            'group_type',
            $value,
        );
    }

    /**
     * @return array<mixed>
     */
    public function options(NovaRequest $request): array
    {
        $options = MigratedCustomer::query()
            ->groupBy('group_type')
            ->pluck('group_type')
            ->toArray();
        Assert::allString($options);

        natcasesort($options);

        return array_reverse($options);
    }
}
