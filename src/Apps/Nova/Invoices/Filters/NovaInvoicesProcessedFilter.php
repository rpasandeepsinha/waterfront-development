<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Invoices\Filters;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\DateFilter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaInvoicesProcessedFilter extends DateFilter
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-filter.invoices_processed');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        assert(is_string($value) || is_null($value));

        return $query->whereDate('sent_to_harbor_at', '=', CarbonImmutable::parse($value));
    }
}
