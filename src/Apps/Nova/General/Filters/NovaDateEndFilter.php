<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\General\Filters;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\DateFilter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaDateEndFilter extends DateFilter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-filter.general.nova-created-at-end');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        assert(is_string($value) || is_null($value));

        return $query->where('created_at', '<=', CarbonImmutable::parse($value)->setTime(23, 59, 59));
    }
}
