<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Products\Resources;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\URL;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Query\Search\SearchableJson;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\Products\Fields\NovaProductSelectField;
use Waterfront\Apps\Nova\Translations\Rules\TranslationKeyHasAllLanguages;
use Waterfront\Domain\Products\Enums\ProductPromotionPlatform;
use Waterfront\Domain\Products\Models\ProductPromotion;
use Waterfront\Infra\Common\DateTimeFormat;

/**
 * @property ProductPromotion $resource
 */
class NovaProductPromotionsResource extends Resource
{
    public static string $model = ProductPromotion::class;

    public static $globallySearchable = false;

    public static function getTranslationKey(): string
    {
        return 'product-promotions';
    }

    /** @return array<mixed> */
    public static function searchableColumns(): array
    {
        return ['product.name', 'platform', new SearchableJson('call_to_action->title')];
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.product-promotions');
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        $platformOptions = [];

        foreach (ProductPromotionPlatform::cases() as $platform) {
            $platformOptions[$platform->value] = self::translate(
                'product_promotion.platform.' . strtolower($platform->name),
            );
        }

        return [
            BelongsTo::make(
                self::translate('subscription.relations.product'),
                'product',
                NovaProductResource::class,
            )->exceptOnForms(),
            NovaProductSelectField::makeForProductId('product_id', null, true)->onlyOnForms()->required(),
            Text::make(
                self::translate('subscription.attributes.start-end_date'),
                fn () => (
                    $this->resource->start_date->format(DateTimeFormat::DUTCH)
                    . ' - '
                    . $this->resource->end_date->format(DateTimeFormat::DUTCH)
                ),
            )
                ->exceptOnForms()
                ->hideFromIndex(),
            Text::make(
                self::translate('product_promotion.create.label.platform'),
                fn () => self::translate('product_promotion.platform.' . strtolower($this->resource->platform->value)),
            )->exceptOnForms(),
            Select::make(
                self::translate('product_promotion.create.label.platform'),
                'platform',
            )
                ->help(self::translate('product_promotion.create.label.platform_help'))
                ->options($platformOptions)
                ->onlyOnForms()
                ->rules(fn ($request) => [
                    'required',
                    Rule::enum(ProductPromotionPlatform::class),
                ]),
            Text::make(
                self::translate('product_promotion.create.label.placement_url'),
                $this->resource->placement_url,
            )
                ->exceptOnForms()
                ->displayUsing(fn () => $this->resource->placement_url),
            Select::make(
                self::translate('product_promotion.create.label.placement_url'),
                'placement_url',
            )
                ->help(self::translate('product_promotion.create.label.placement_url_help'))
                ->options(['/dashboard' => '/dashboard'])
                ->onlyOnForms()
                ->rules('required'),
            Text::make(
                self::translate('product_promotion.create.label.title'),
                'call_to_action->title',
            )
                ->help(self::translate('nova.fields.help.translation_key_required'))
                ->placeholder('product_promotion.create.label.price_description')
                ->rules([
                    'required',
                    new TranslationKeyHasAllLanguages(),
                ]),
            Text::make(
                self::translate('product_promotion.create.label.description'),
                'call_to_action->description',
            )
                ->hideFromIndex()
                ->help(self::translate('nova.fields.help.translation_key_required'))
                ->placeholder('product_promotion.create.label.price_description')
                ->rules([
                    'sometimes',
                    'required',
                    new TranslationKeyHasAllLanguages(),
                ]),
            Text::make(
                self::translate('product_promotion.create.label.price_description'),
                'call_to_action->price_description',
            )
                ->rules([
                    'sometimes',
                    'required',
                    new TranslationKeyHasAllLanguages(),
                ])
                ->placeholder('product_promotion.create.label.price_description')
                ->hideFromIndex()
                ->help(
                    self::translate('nova.fields.help.translation_key_required'),
                ),
            Text::make(
                self::translate('product_promotion.create.label.button_text'),
                'call_to_action->button_text',
            )
                ->hideFromIndex()
                ->placeholder('product_promotion.create.label.price_description')
                ->help(self::translate('nova.fields.help.translation_key_required'))
                ->rules([
                    'sometimes',
                    'required',
                    new TranslationKeyHasAllLanguages(),
                ]),
            URL::make('destination_url', 'call_to_action->destination_url')
                ->displayUsing(fn () => Arr::get($this->resource->call_to_action, 'destination_url', 'N/A'))
                ->exceptOnForms()
                ->hideFromIndex(),
            Text::make(
                self::translate('product_promotion.create.label.destination_url'),
                'call_to_action->destination_url',
            )
                ->help(
                    self::translate('product_promotion.create.label.destination_url_help'),
                )
                ->placeholder('https://www.google.com')
                ->onlyOnForms()
                ->required()
                ->rules('url', 'url:https'),
            Number::make(
                self::translate('product_promotion.create.label.weight'),
                'weight',
            )->rules(['required', 'between: 1,100']),
            Date::make(self::translate('subscription.attributes.start_date'), 'start_date')
                ->sortable()
                ->hideFromDetail(),
            Date::make(self::translate('subscription.attributes.end_date'), 'end_date')->sortable()->hideFromDetail(),
        ];
    }
}
