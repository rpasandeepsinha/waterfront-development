<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Products\Resources;

use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Customers\Resources\NovaCustomerResource;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Domain\Products\Models\ProductGroup;

/** @property ProductGroup $resource */
class NovaProductGroupResource extends Resource
{
    public static string $model = ProductGroup::class;

    public static $globallySearchable = false;

    /**
     * @var array<mixed>
     */
    public static $search = [
        'name',
        'slug',
    ];

    public static function getTranslationKey(): string
    {
        return 'product-group';
    }

    public function title(): string
    {
        return $this->resource->name;
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.groups');
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->hideFromIndex(),
            Text::make(self::translate('product-group.attributes.name'), 'name')->rules('required')->sortable(),
            Text::make(self::translate('product-group.attributes.slug'), 'slug')
                ->rules('required', 'alpha_dash')
                ->sortable()
                ->hideWhenUpdating(),
            Number::make(self::translate('product-group.attributes.ledger_code'), 'ledger_code')
                ->rules('required')
                ->sortable(),
            Number::make(self::translate('product-group.attributes.default_rate'), 'default_rate_percentage')
                ->rules('required')
                ->min(0)
                ->max(100)
                ->sortable(),
            HasMany::make(
                self::translate('product.plural'),
                'products',
                NovaProductResource::class,
            ),
            BelongsToMany::make(
                self::translate('product-group.relations.customers'),
                'customers',
                NovaCustomerResource::class,
            )
                ->fields(fn (): array => [
                    Number::make(self::translate('customer.discount'), 'discount')
                        ->min(0)
                        ->max(100)
                        ->step(0.01)
                        ->help(self::translate('customer.info.discount')),
                ])
                ->singularLabel(self::translate('customer.singular')),
            Number::make(self::translate('product-group.default_billing_period'), 'default_billing_period')
                ->rules('required')
                ->min(0)
                ->nullable()
                ->hideFromIndex(),
            Number::make(self::translate('product-group.default_contract_period'), 'default_contract_period')
                ->rules('required')
                ->min(0)
                ->nullable()
                ->hideFromIndex(),
        ];
    }
}
