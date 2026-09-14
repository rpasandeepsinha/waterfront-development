<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Customers\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Laravel\Nova\Filters\BooleanFilter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaCustomerTypeFilter extends BooleanFilter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-filter.customer_type');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where(function (Builder $builder) use ($value): void {
            assert(is_array($value));
            if (Arr::get($value, 'migration') === true) {
                $builder->whereHas('migratedCustomers');
            }

            if (Arr::get($value, 'non-migration') === true) {
                $builder->whereDoesntHave('migratedCustomers');
            }
        });
    }

    /**
     * @return array<string>
     */
    public function options(NovaRequest $request): array
    {
        return [
            $this->translator->translate('nova-filter.customer_types.migration') => 'migration',
            $this->translator->translate('nova-filter.customer_types.non-migration') => 'non-migration',
        ];
    }
}
