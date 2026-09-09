<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Translations\Resources;

use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\General\Traits\RedirectBackToResourceIndex;
use Waterfront\Apps\Nova\Translations\Filters\NovaTranslationLanguageFilter;
use Waterfront\Apps\Nova\Translations\Filters\NovaTranslationStatusFilter;
use Waterfront\Apps\Nova\Translations\Rules\TranslationStringsUnique;
use Waterfront\Domain\Translations\Models\TranslationString;

/** @property TranslationString $resource */
class NovaTranslationStringResource extends Resource
{
    use RedirectBackToResourceIndex;

    public static string $model = TranslationString::class;

    public static $globallySearchable = false;

    /**
     * @var array<mixed>
     */
    public static $with = ['language', 'translationkey'];

    /**
     * @var array<mixed>
     */
    public static $search = [
        'translated_string',
    ];

    public static function getTranslationKey(): string
    {
        return 'translationstring';
    }

    public function title(): string
    {
        return $this->resource->translated_string ?? (string) $this->resource->id;
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.language_strings');
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make(self::translate('ID'), 'id')->sortable(),
            BelongsTo::make('Translation', 'language', NovaTranslationResource::class),
            BelongsTo::make('TranslationKey', 'translationkey', NovaTranslationKeyResource::class)->searchable()->rules(new TranslationStringsUnique()),
            Textarea::make(self::translate(self::getTranslationKey() . '.attributes.translated_string'), 'translated_string')->sortable(),
        ];
    }

    /**
     * @return array<int, HtmlCard>
     */
    public function cards(NovaRequest $request): array
    {
        return [
            new HtmlCard()
                ->width('full')
                ->html('<p class="text-80 font-light mt-2">' . self::translate('language.nova_info.translationstring_overview') . '</p>'),
        ];
    }

    /**
     * @return array<int, NovaTranslationLanguageFilter|NovaTranslationStatusFilter>
     */
    public function filters(NovaRequest $request): array
    {
        return [
            resolve(NovaTranslationLanguageFilter::class),
            resolve(NovaTranslationStatusFilter::class),
        ];
    }
}
