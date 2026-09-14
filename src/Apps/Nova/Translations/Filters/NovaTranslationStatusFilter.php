<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Translations\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\BooleanFilter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaTranslationStatusFilter extends BooleanFilter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-filter.translation_status');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        if (is_array($value) && ! $value['translation_status']) {
            return $query;
        }

        return $query->whereNull('translated_string');
    }

    /**
     * @return string[]
     */
    public function options(NovaRequest $request): array
    {
        return [
            $this->translator->translate('nova-filter.translation_statuses.untranslated') => 'translation_status',
        ];
    }
}
