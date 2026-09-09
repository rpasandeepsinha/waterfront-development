<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Translations\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Translations\Models\TranslationKey;

class NovaTranslationKeySourceFilter extends Filter
{
    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('source', $value);
    }

    /** @return mixed[] */
    public function options(NovaRequest $request): array
    {
        return TranslationKey::all()->pluck('source', 'source')->toArray();
    }
}
