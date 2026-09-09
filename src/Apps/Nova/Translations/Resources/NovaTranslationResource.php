<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Translations\Resources;

use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\General\Traits\RedirectBackToResourceIndex;
use Waterfront\Apps\Nova\Translations\Actions\NovaUpdateTranslationsToObjectStorageAction;
use Waterfront\Domain\Translations\Models\TranslationLanguage;

/** @property TranslationLanguage $resource */
class NovaTranslationResource extends Resource
{
    use RedirectBackToResourceIndex;

    public static string $model = TranslationLanguage::class;

    public static $globallySearchable = false;

    /**
     * @var array<mixed>
     */
    public static $search = [
        'id',
        'locale',
        'display_name',
    ];

    public static function getTranslationKey(): string
    {
        return 'language';
    }

    public function title(): string
    {
        return $this->resource->display_name;
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.languages');
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make(self::translate(self::getTranslationKey() . '.attributes.name'), 'display_name')->sortable(),
            Text::make(self::translate(self::getTranslationKey() . '.attributes.locale'), 'locale')->sortable(),
            NovaBoolField::make(self::translate(self::getTranslationKey() . '.attributes.active'), 'active')->sortable(),
            NovaBoolField::make(self::translate(self::getTranslationKey() . '.attributes.default'), 'default')->sortable(),
        ];
    }

    /** @return array<int, NovaUpdateTranslationsToObjectStorageAction> */
    public function actions(NovaRequest $request): array
    {
        /** @var NovaUpdateTranslationsToObjectStorageAction $novaUpdateTranslationsAction */
        $novaUpdateTranslationsAction = resolve(NovaUpdateTranslationsToObjectStorageAction::class);
        return [
            $novaUpdateTranslationsAction->standalone(),
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
                ->html('<p class="text-80 font-light mt-2">' . self::translate('language.nova_info.translation_overview') . '</p>'),
        ];
    }
}
