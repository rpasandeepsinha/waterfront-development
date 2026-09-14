<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\OneTimeServices\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaInvoicedFilter extends Filter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-filter.one-time-service.field.invoiced');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        if ($value === 'yes') {
            $query->whereHas('invoices');
        } else {
            $query->whereDoesntHave('invoices');
        }

        return $query;
    }

    /**
     * @return array<string>
     */
    public function options(NovaRequest $request): array
    {
        return [
            $this->translator->translate('nova-filter.one-time-service.field.invoiced.yes') => 'yes',
            $this->translator->translate('nova-filter.one-time-service.field.invoiced.no') => 'no',
        ];
    }
}
