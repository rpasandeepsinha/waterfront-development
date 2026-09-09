<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\VPS\Resources;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\Products\Resources\NovaProductResource;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\VPS\Models\EnvironmentProduct;

/** @property EnvironmentProduct $resource */
class NovaCloudStackEnvironmentProductResource extends Resource
{
    public static string $model = EnvironmentProduct::class;

    public static $globallySearchable = false;

    public static function getTranslationKey(): string
    {
        return 'cloudstack-environment-products';
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.cloudstack_environment_products');
    }

    public function title(): string
    {
        return $this->resource->environment->name . ' - ' . $this->resource->product->name;
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            BelongsTo::make(
                self::translate('cloudstack-environments.singular'),
                'environment',
                NovaCloudStackEnvironmentResource::class
            ),
            BelongsTo::make(
                self::translate('product.singular'),
                'product',
                NovaProductResource::class
            )
            ->relatableQueryUsing(fn (NovaRequest $request, Builder $query): Builder => $query->whereHas('productGroup', fn (Builder $query) => $query->where('slug', ProductGroupType::VPS))),
            Text::make(self::translate('cloudstack-environment-products.attributes.product_identifier'), 'product_identifier')
                ->rules('uuid')
                ->help(self::translate('cloudstack-environment-products.attributes_help.product_identifier'))
                ->required(),
        ];
    }
}
