<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Products\Resources;

use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\HasManyThrough;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\General\Traits\ResolvesActionsAndFilters;
use Waterfront\Apps\Nova\Microsoft365\Resources\NovaMicrosoft365KpnProductsResource;
use Waterfront\Apps\Nova\Products\Actions\NovaAddPremiumDomainProductAction;
use Waterfront\Apps\Nova\Products\Actions\NovaExportProductsToBucketAction;
use Waterfront\Apps\Nova\Products\Filters\NovaProductGroupFilter;
use Waterfront\Apps\Nova\VPS\Resources\NovaCloudStackEnvironmentProductResource;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;

/** @property Product $resource */
class NovaProductResource extends Resource
{
    use ResolvesActionsAndFilters;

    public static string $model = Product::class;

    public static $globallySearchable = false;

    public static $perPageViaRelationship = 10;

    /**
     * @var array<mixed>
     */
    public static $search = [
        'name',
        'description',
        'slug',
    ];

    /**
     * @var array<mixed>
     */
    public static $with = ['productGroup', 'microsoft365KpnProducts'];

    public static function getTranslationKey(): string
    {
        return 'product';
    }

    public function title(): string
    {
        return $this->resource->name;
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.products');
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        $productGroupSlug = null;
        $isFree = false;

        if ($this->resource->exists) {
            $productGroupSlug = $this->resource->productGroup->slug;
            $isFree = str_starts_with($this->resource->slug, 'free-')
                || str_starts_with($this->resource->slug, 'free_')
                || str_ends_with($this->resource->slug, '-free')
                || str_ends_with($this->resource->slug, '_free');
        }

        return [
            BelongsTo::make(
                self::translate('product.relations.product_group'),
                'productGroup',
                NovaProductGroupResource::class
            )->sortable(),

            Text::make(self::translate('product.attributes.uuid'), 'uuid')
                ->onlyOnDetail()
                ->copyable(),

            Text::make(self::translate('product.attributes.name'), 'name')
                ->rules('required')
                ->sortable(),

            Text::make(self::translate('product.attributes.slug'), 'slug')
                ->rules('required', 'alpha_dash')
                ->readonly(fn (): bool => $isFree)
                ->hideWhenUpdating()
                ->sortable()
                ->help(self::translate('product.attributes.slug-helper')),

            Textarea::make(self::translate('product.attributes.description'), 'description'),

            NovaBoolField::make(self::translate('product.attributes.orderable'), 'orderable')
                ->sortable(),

            Number::make(self::translate('product.attributes.weight'), 'weight')
                ->rules('required')
                ->sortable(),

            HasMany::make(
                self::translate('product-spec.plural'),
                'productSpecs',
                NovaProductSpecResource::class
            ),

            /**
             * @see Product::allowedProductUpgrades()
             */
            HasMany::make(
                ucfirst(self::translate('product.relations.product_allowed_changes.upgrade')),
                'allowedProductUpgrades',
                NovaProductAllowedChangeResource::class,
            ),

            /**
             * @see Product::allowedProductDowngrades()
             */
            HasMany::make(
                ucfirst(self::translate('product.relations.product_allowed_changes.downgrade')),
                'allowedProductDowngrades',
                NovaProductAllowedChangeResource::class
            ),

            /**
             * @see Product::allowedProductReinstalls()
             */
            HasMany::make(
                ucfirst(self::translate('product.relations.product_allowed_changes.reinstall')),
                'allowedProductReinstalls',
                NovaProductAllowedChangeResource::class
            )
            ->onlyOnDetail(),

            /** @uses Product::productPromotions() */
            HasMany::make(
                ucfirst(self::translate('product-promotions.plural')),
                'productPromotions',
                NovaProductPromotionsResource::class
            ),

            /**
             * @see Product::cloudStackEnvironments()
             */
            HasMany::make(
                'CloudStack environments',
                'cloudstackEnvironments',
                NovaCloudStackEnvironmentProductResource::class
            )->canSee(
                fn (): bool => $productGroupSlug === ProductGroupType::CLOUDSTACK_VIRTUAL_MACHINE
                    || $productGroupSlug === ProductGroupType::CLOUDSTACK_OS
                    || $productGroupSlug === ProductGroupType::VPS
            ),

            BelongsToMany::make(
                self::translate('product-parent.attach'),
                'parents',
                NovaProductResource::class
            )
                ->onlyOnDetail()
                ->canSee(fn () => $productGroupSlug === ProductGroupType::ADD_ON),

            /**
             * @see Product::microsoft365KpnProducts()
             */
            HasManyThrough::make(
                self::translate('microsoft365-kpn-products.plural'),
                'microsoft365KpnProducts',
                NovaMicrosoft365KpnProductsResource::class
            )->onlyOnDetail(),
        ];
    }

    /**
     * @return array<int,Action>
     */
    public function actions(NovaRequest $request): array
    {
        /** @var NovaExportProductsToBucketAction $novaProductsToStorageAction */
        $novaProductsToStorageAction = $this->resolveAction(NovaExportProductsToBucketAction::class);

        return [
            $novaProductsToStorageAction->standalone(),
            $this->resolveAction(NovaAddPremiumDomainProductAction::class)->standalone(),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(NovaRequest $request): array
    {
        $filters = [];

        if (! $request->viaRelationship()) {
            $filters[] = $this->resolveFilter(NovaProductGroupFilter::class);
        }

        return $filters;
    }
}
