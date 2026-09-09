<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Translations\Resources;

use Illuminate\Validation\Rule;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\General\Traits\RedirectBackToResourceIndex;
use Waterfront\Apps\Nova\Translations\Filters\NovaTranslationKeySourceFilter;
use Waterfront\Domain\Translations\Enums\TranslationPlatforms;
use Waterfront\Domain\Translations\Models\TranslationKey;

/** @property TranslationKey $resource */
class NovaTranslationKeyResource extends Resource
{
    use RedirectBackToResourceIndex;

    public static string $model = TranslationKey::class;

    public static $globallySearchable = false;

    /**
     * @var array<mixed>
     */
    public static $with = ['translationStrings'];

    /**
     * @var array<mixed>
     */
    public static $search = [
        'key',
        'source',
    ];

    public static function getTranslationKey(): string
    {
        return 'translationkey';
    }

    public function title(): string
    {
        return "{$this->resource->key}";
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.language_keys');
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        $translationPlatformCases = array_map(fn (TranslationPlatforms $translationPlatform): string => $translationPlatform->value, TranslationPlatforms::cases());
        return [
            Text::make(self::translate(self::getTranslationKey() . '.attributes.key'), 'key')->sortable()
                ->creationRules([
                    Rule::unique('translation_keys')->where(fn ($query) => $query->where('key', $request->key)
                        ->where('source', $request->source)),
                ]),
            Select::make(self::translate(self::getTranslationKey() . '.attributes.source'), 'source')
                ->options(array_combine($translationPlatformCases, $translationPlatformCases))
                ->hideFromIndex(),
            HasMany::make(
                self::translate('translationKeys.translationStrings'),
                'translationStrings',
                NovaTranslationStringResource::class
            )->onlyOnDetail(),
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
                ->html('<p class="text-80 font-light mt-2">' . self::translate('language.nova_info.translationkey_overview') . '</p>'),
        ];
    }

    /**
     * @return array<NovaTranslationKeySourceFilter>
     */
    public function filters(NovaRequest $request): array
    {
        return [
            new NovaTranslationKeySourceFilter(),
        ];
    }
}
