<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Translations\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Translations\Models\TranslationLanguage;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaTranslationLanguageFilter extends Filter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-filter.translation_language');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('language_id', $value);
    }

    /** @return mixed[] */
    public function options(NovaRequest $request): array
    {
        return TranslationLanguage::all()->pluck('id', 'display_name')->toArray();
    }
}
