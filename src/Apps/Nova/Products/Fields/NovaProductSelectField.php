<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Products\Fields;

use Illuminate\Support\Collection;
use Laravel\Nova\Fields\Select;
use phpDocumentor\Reflection\Types\Boolean;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaProductSelectField
{
    public static function make(string $attribute, ?ProductGroup $productGroup = null, ?Boolean $orderable = null): Select
    {
        $translator = resolve(TranslatorInterface::class);
        return Select::make($translator->translate('subscription.relations.product'), $attribute)
            ->required()
            ->options(
                function () use ($productGroup, $orderable): Collection {
                    $builder = Product::query()
                        ->selectRaw('products.name as productname, products.uuid as productuuid, product_groups.name as productgroupname')
                        ->withoutGlobalScope('order')
                        ->join('product_groups', 'product_groups.id', '=', 'products.product_group_id')
                        ->orderByRaw('LOWER(product_groups.name), LOWER(products.name)');

                    if ($productGroup !== null) {
                        $builder->where('product_groups.id', $productGroup->id);
                    }

                    if ($orderable !== null) {
                        $builder->where('products.orderable', $orderable);
                    }

                    return $builder->get()
                        ->map(
                            fn (Product $product): array => [ // @phpstan-ignore argument.unresolvableType
                                    'label' => $product->productname, // @phpstan-ignore property.notFound
                                    'value' => $product->productuuid, // @phpstan-ignore property.notFound
                                    'group' => $product->productgroupname, // @phpstan-ignore property.notFound
                                ]
                        );
                }
            );
    }

    public static function makeForProductId(string $attribute, ?ProductGroup $productGroup = null, ?bool $orderable = null): Select
    {
        $translator = resolve(TranslatorInterface::class);
        return Select::make($translator->translate('subscription.relations.product'), $attribute)
            ->options(
                function () use ($productGroup, $orderable): Collection {
                    $builder = Product::query()
                        ->selectRaw('products.name as productname, products.id as productid, product_groups.name as productgroupname')
                        ->withoutGlobalScope('order')
                        ->join('product_groups', 'product_groups.id', '=', 'products.product_group_id')
                        ->orderByRaw('LOWER(product_groups.name), LOWER(products.name)');

                    if ($productGroup !== null) {
                        $builder->where('product_groups.id', $productGroup->id);
                    }

                    if ($orderable !== null) {
                        $builder->where('products.orderable', $orderable);
                    }

                    return $builder->get()
                        ->map(
                            fn (Product $product): array => [ // @phpstan-ignore argument.unresolvableType
                                'label' => $product->productname, // @phpstan-ignore property.notFound
                                'value' => $product->productid, // @phpstan-ignore property.notFound
                                'group' => $product->productgroupname, // @phpstan-ignore property.notFound
                            ]
                        );
                }
            );
    }
}
