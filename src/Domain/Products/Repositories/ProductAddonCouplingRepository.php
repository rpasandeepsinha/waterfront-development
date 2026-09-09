<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Repositories;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Collection;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductAddonCoupling;

class ProductAddonCouplingRepository
{
    /**
     * @return Collection<int, ProductAddonCoupling>
     */
    public function getAddonProductsForParentProduct(Product $product): Collection
    {
        return ProductAddonCoupling::query()->with(['addonProduct', 'parentProduct'])->where('parent_product_id', $product->id)->get();
    }

    /**
     * @return Collection<int, ProductAddonCoupling>
     */
    public function getOrderableAddonProductsForParentProduct(Product $product): Collection
    {
        return ProductAddonCoupling::query()->with(['addonProduct', 'parentProduct'])->where('parent_product_id', $product->id)->whereHas(
            'addonProduct',
            fn (Builder $builder) => $builder->where('orderable', true)
        )->get();
    }

    public function existsForParentIdAndAddonId(int $parentProductId, int $addonProductId): bool
    {
        return ProductAddonCoupling::query()->where('parent_product_id', $parentProductId)->where('addon_product_id', $addonProductId)->exists();
    }
}
