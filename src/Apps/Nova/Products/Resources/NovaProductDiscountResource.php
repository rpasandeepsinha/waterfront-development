<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Products\Resources;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Customers\Resources\NovaCustomerResource;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\ProductDiscount;

/** @property ProductDiscount $resource */
class NovaProductDiscountResource extends Resource
{
    public static string $model = ProductDiscount::class;

    public static $globallySearchable = false;

    /**
     * @var array<mixed>
     */
    public static $search = [
        'name',
        'description',
    ];

    public static function getTranslationKey(): string
    {
        return 'product-discount';
    }

    public function title(): string
    {
        return $this->resource->name;
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.discounts');
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        $productGroupFilter = fn (Builder $productGroupQuery): Builder => $productGroupQuery->where(
            'slug',
            ProductGroupType::VOLUME_DISCOUNT,
        );
        $productFilter = fn (NovaRequest $request, Builder $query) => $query
            ->select('products.*')
            ->whereHas('productGroup', $productGroupFilter)
            ->leftJoin('product_discounts', 'products.id', '=', 'product_discounts.product_id')
            ->whereNull('product_discounts.id');

        return [
            ID::make()->hideFromIndex(),
            Text::make(self::translate('product-discount.attributes.name'), 'name')->sortable(),
            Textarea::make(self::translate('product-discount.attributes.description'), 'description')->sortable(),

            BelongsTo::make(
                self::translate('product-discount.relations.product'),
                'product',
                NovaProductResource::class,
            )
                ->nullable()
                ->help(self::translate('product-discount.help.product'))
                ->sortable()
                ->relatableQueryUsing($productFilter),
            BelongsToMany::make(
                self::translate('product-discount.relations.customers'),
                'customers',
                NovaCustomerResource::class,
            )
                ->searchable()
                ->singularLabel(self::translate('customer.singular')),
        ];
    }

    public function authorizedToForceDelete(Request $request)
    {
        return false;
    }
}
