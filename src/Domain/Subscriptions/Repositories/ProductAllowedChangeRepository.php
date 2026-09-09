<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Repositories;

use Illuminate\Support\Collection;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Models\ProductAllowedChange;

class ProductAllowedChangeRepository
{
    /**
     * @return Collection<int, Product>
     */
    public function getPotentialUpgrades(Product $product): Collection
    {
        /** @var Collection<int, Product> */
        return ProductAllowedChange::query()
            ->where('from_product_id', $product->id)
            ->where('change_type', ProductChangeType::UPGRADE)
            ->orderBy('display_order')
            ->with(['toProduct.productSpecs', 'toProduct.productGroup'])
            ->get()
            ->map(fn ($sq): ?Product => $sq->toProduct)
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, Product>
     */
    public function getPotentialDowngrades(Product $product): Collection
    {
        /** @var Collection<int, Product> */
        return ProductAllowedChange::query()
            ->where('from_product_id', $product->id)
            ->where('change_type', ProductChangeType::DOWNGRADE)
            ->orderBy('display_order')
            ->with(['toProduct.productSpecs', 'toProduct.productGroup'])
            ->get()
            ->map(fn ($sq): ?Product => $sq->toProduct)
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, Product>
     */
    public function getPotentialUpgradesForCustomer(Product $product): Collection
    {
        /** @var Collection<int, Product> */
        return ProductAllowedChange::query()
            ->where('from_product_id', $product->id)
            ->where('change_type', ProductChangeType::UPGRADE)
            ->where('is_available_for_customer', true)
            ->join('products', 'products.id', '=', 'product_allowed_changes.to_product_id')
            ->where('products.orderable', true)
            ->orderBy('display_order')
            ->with(['toProduct.productSpecs', 'toProduct.productGroup'])
            ->get()
            ->map(fn ($sq): ?Product => $sq->toProduct)
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, Product>
     */
    public function getPotentialDowngradesForCustomer(Product $product): Collection
    {
        /** @var Collection<int, Product> */
        return ProductAllowedChange::query()
            ->where('from_product_id', $product->id)
            ->where('change_type', ProductChangeType::DOWNGRADE)
            ->where('is_available_for_customer', true)
            ->join('products', 'products.id', '=', 'product_allowed_changes.to_product_id')
            ->where('products.orderable', true)
            ->orderBy('display_order')
            ->with(['toProduct.productSpecs', 'toProduct.productGroup'])
            ->get()
            ->map(fn ($sq): ?Product => $sq->toProduct)
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, Product>
     */
    public function getPotentialReinstalls(Product $product): Collection
    {
        /** @var Collection<int, Product> */
        return ProductAllowedChange::query()
            ->where('from_product_id', $product->id)
            ->where('change_type', ProductChangeType::REINSTALL)
            ->orderBy('display_order')
            ->with(['toProduct.productSpecs', 'toProduct.productGroup'])
            ->get()
            ->map(fn ($sq): ?Product => $sq->toProduct)
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, Product>
     */
    public function getPotentialReinstallsForCustomer(Product $product): Collection
    {
        /** @var Collection<int, Product> */
        return ProductAllowedChange::query()
            ->where('from_product_id', $product->id)
            ->where('change_type', ProductChangeType::REINSTALL)
            ->where('is_available_for_customer', true)
            ->whereHas('toProduct', fn ($query) => $query->whereNull('deleted_at'))
            ->whereHas('fromProduct', fn ($query) => $query->whereNull('deleted_at'))
            ->join('products', 'products.id', '=', 'product_allowed_changes.to_product_id')
            ->where('products.orderable', true)
            ->orderBy('display_order')
            ->with(['toProduct.productSpecs', 'toProduct.productGroup'])
            ->get()
            ->map(fn ($sq): ?Product => $sq->toProduct)
            ->filter()
            ->values();
    }

    public function isProductChangeAllowed(ProductChangeType $changeType, Product $fromProduct, Product $toProduct): bool
    {
        return ProductAllowedChange::query()
            ->where('change_type', $changeType)
            ->where('from_product_id', $fromProduct->id)
            ->where('to_product_id', $toProduct->id)
            ->exists();
    }
}
