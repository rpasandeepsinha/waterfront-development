<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Microsoft365\Resources;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\Products\Resources\NovaProductResource;
use Waterfront\Domain\Microsoft365\Models\Microsoft365KpnProduct;
use Waterfront\Domain\Products\Enums\ProductGroupType;

/** @property Microsoft365KpnProduct $resource */
class NovaMicrosoft365KpnProductsResource extends Resource
{
    public static string $model = Microsoft365KpnProduct::class;

    public static $globallySearchable = false;

    /** @var array<mixed> */
    public static $with = ['product'];

    /** @var array<mixed> */
    public static $search = [
        'kpn_product_code',
    ];

    public static function getTranslationKey(): string
    {
        return 'microsoft365-kpn-products';
    }

    public function title(): string
    {
        return $this->resource->kpn_product_code;
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.kpn-products-codes');
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        $productGroupFilter = fn (Builder $productGroupQuery): Builder => $productGroupQuery->whereIn('slug', [ProductGroupType::MICROSOFT_365]);
        $productPriceFilter = fn (NovaRequest $request, Builder $query) => $query->whereHas('productGroup', $productGroupFilter);

        return [
            Text::make(self::translate('microsoft365-kpn-products.kpn-product-code'), 'kpn_product_code')->required()->sortable(),
            BelongsTo::make(self::translate('product.singular'), 'product', NovaProductResource::class)->relatableQueryUsing($productPriceFilter),
            Select::make(self::translate('subscription.attributes.contract_period'), 'contract_period')->required()->options(['1' => '1', '12' => '12']),
        ];
    }
}
