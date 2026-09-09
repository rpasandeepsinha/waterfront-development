<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Invoices\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Laravel\Nova\Filters\BooleanFilter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaInvoicesIsProcessedFilter extends BooleanFilter
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-filter.invoices_is_processed');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        assert(is_array($value));
        if ((bool) Arr::get($value, 'true')) {
            $query->whereNotNull('sent_to_harbor_at');
        }

        if ((bool) Arr::get($value, 'false')) {
            $query->whereNull('sent_to_harbor_at');
        }

        return $query;
    }

    /**
     * @return array<string, string>
     */
    public function options(NovaRequest $request): array
    {
        return [
            $this->translator->translate('nova-filter.invoices_is_processed_statuses.true')  => 'true',
            $this->translator->translate('nova-filter.invoices_is_processed_statuses.false') => 'false',
        ];
    }
}
