<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaSubscriptionMigrationGroupTypeFilter extends Filter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-filter.subscriptions.migration-group-type');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query
            ->has('migratedSubscriptions') // So we only get subscriptions from a migration, not any created after migration.
            ->whereRelation('customer.migratedCustomers', 'group_type', $value);
    }

    /**
     * @return array<mixed>
     */
    public function options(NovaRequest $request): array
    {
        return MigratedCustomer::query()->groupBy('group_type')->orderBy('group_type')->pluck('group_type')->toArray();
    }
}
