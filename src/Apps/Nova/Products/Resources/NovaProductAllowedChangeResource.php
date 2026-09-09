<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Products\Resources;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\URL;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Models\ProductAllowedChange;

/**
 * @property ProductAllowedChange $resource
 *
 */
class NovaProductAllowedChangeResource extends Resource
{
    public static string $model = ProductAllowedChange::class;

    public static $globallySearchable = false;

    public static $perPageViaRelationship = 10;

    /**
     * @var array<mixed>
     */
    public static $search = [
        'fromProduct.name',
        'fromProduct.slug',
        'toProduct.name',
        'toProduct.slug',
    ];

    /**
     * @var array<mixed>
     */
    public static $with = [
        'fromProduct',
        'toProduct',
    ];

    public static function getTranslationKey(): string
    {
        return 'product_allowed_changes';
    }

    public function title(): string
    {
        return sprintf(self::translate('nova.product-allowed-change.title'), $this->resource->fromProduct?->name, $this->resource->toProduct?->name);
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.upgrades-downgrades');
    }

    /**
     * @return Field[]
     */
    public function fields(NovaRequest $request): array
    {
        // Show different fields when we reach the Allowed changes through Products
        if ($request->viaRelationship() && $request->viaResource() === NovaProductResource::class) {
            return $this->createFieldsFromProductResource($request);
        }

        return [
            ID::make()->sortable(),
            BelongsTo::make(
                self::translate('nova-resource-labels.product_changes.attributes.from_product'),
                'fromProduct',
                NovaProductResource::class
            )->sortable(),
            BelongsTo::make(
                self::translate('nova-resource-labels.product_changes.attributes.to_product'),
                'toProduct',
                NovaProductResource::class
            )->sortable(),
            Select::make(
                self::translate('nova-resource-labels.product_changes.attributes.change_type'),
                'change_type'
            )
                ->options([
                    ProductChangeType::UPGRADE->value => ucfirst(ProductChangeType::UPGRADE->value),
                    ProductChangeType::DOWNGRADE->value => ucfirst(ProductChangeType::DOWNGRADE->value),
                    ProductChangeType::REINSTALL->value => ucfirst(ProductChangeType::REINSTALL->value),
                ])
                ->displayUsingLabels()
                ->sortable()
                ->rules('required'),
            Number::make(
                self::translate('nova-resource-labels.product_changes.attributes.display_order'),
                'display_order'
            )->sortable()
                ->rules('required'),
            Boolean::class::make(
                self::translate('nova-resource-labels.product_changes.attributes.is_available_for_customer'),
                'is_available_for_customer'
            )->sortable(),
        ];
    }

    public static function redirectAfterCreate(NovaRequest $request, $resource): URL|string
    {
        if ($request->viaRelationship() && $request->viaResource() === NovaProductResource::class) {
            return '/resources/' . $request->viaResource . '/' . $request->viaResourceId;
        }
        return parent::redirectAfterCreate($request, $resource);
    }

    public static function redirectAfterUpdate(NovaRequest $request, $resource): URL|string
    {
        if ($request->viaRelationship() && $request->viaResource() === NovaProductResource::class) {
            return '/resources/' . $request->viaResource . '/' . $request->viaResourceId;
        }
        return parent::redirectAfterUpdate($request, $resource);
    }

    /**
     * @param array<string, string> $orderings
     */
    protected static function applyOrderings(Builder $query, array $orderings): Builder
    {
        if (count($orderings) === 0) {
            $orderings['display_order'] = 'asc';
        }
        return parent::applyOrderings($query, $orderings);
    }

    /**
     * If we enter this resource form from the Product resource, we need to show different fields.
     * The 'From Product' should be hidden and filled with the product id we came from and the
     * 'ChangeType' will be set based on the relationship we came from, upgrade or downgrade.
     *
     * @return Field[]
     */
    private function createFieldsFromProductResource(NovaRequest $request): array
    {
        $fromProductId = $request->viaResourceId;
        $type = match ($request->viaRelationship) {
            'allowedProductUpgrades' => ProductChangeType::UPGRADE,
            'allowedProductDowngrades' => ProductChangeType::DOWNGRADE,
            'allowedProductReinstalls' => ProductChangeType::REINSTALL,
            default => null, // just a fallback for Phpstan, should never occur.
        };

        return [
            Hidden::make('from_product', 'from_product_id')->default($fromProductId)->onlyOnForms(),
            Hidden::make('change_type', 'change_type')->default($type)->onlyOnForms(),
            BelongsTo::make(
                self::translate('nova-resource-labels.product_changes.attributes.to_product'),
                'toProduct',
                NovaProductResource::class
            )->sortable(),
            Number::make(
                self::translate('nova-resource-labels.product_changes.attributes.display_order'),
                'display_order'
            )->sortable()
                ->rules('required'),
            Boolean::class::make(
                self::translate('nova-resource-labels.product_changes.attributes.is_available_for_customer'),
                'is_available_for_customer'
            )->sortable(),
        ];
    }
}
