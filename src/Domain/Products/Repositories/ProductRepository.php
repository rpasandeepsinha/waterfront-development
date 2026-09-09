<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use SortDirection;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Subscriptions\Models\ProductAllowedChange;

class ProductRepository
{
    /**
     * @return Collection<int, Product>
     */
    public function productSearchBasedOnNameOrSlug(string $searchTerm): Collection
    {
        return Product::where('slug', 'ilike', "%$searchTerm%")
            ->orWhere('name', 'ilike', "%$searchTerm%")
            ->with('productGroup')
            ->get();
    }

    public function findProductByName(string $name): Product
    {
        return Product::where('name', $name)->firstOrFail();
    }

    public function findProductBySlug(string $slug): Product
    {
        return Product::where('slug', $slug)->firstOrFail();
    }

    /**
     * @param string[] $slugs
     *
     * @return Collection<int, Product>
     */
    public function getProductsBySlugs(array $slugs): Collection
    {
        return Product::whereIn('slug', $slugs)->get();
    }

    public function findProductByUuid(string $uuid): Product
    {
        return Product::where('uuid', $uuid)->firstOrFail();
    }

    public function findProductById(int $id): Product
    {
        return Product::where('id', $id)->firstOrFail();
    }

    public function findProductsByProductGroupSlugAndProductSlug(ProductGroupType $productGroup, string $productSlug): Product|null
    {
        return Product::query()
            ->whereHas('productGroup', function (Builder $query) use ($productGroup): void {
                $query->where('slug', $productGroup->value);
            })
            ->where('slug', $productSlug)
            ->first();
    }

    public function findProductByProductGroupSlugAndWildcardProductSlug(ProductGroupType $productGroup, string $productSlug): Product|null
    {
        return Product::query()
            ->whereHas('productGroup', function (Builder $query) use ($productGroup): void {
                $query->where('slug', $productGroup->value);
            })
            ->where('slug', 'like', $productSlug)
            ->first();
    }

    public function slugExistsForGroup(string $slug, ProductGroupType $group): bool
    {
        return Product::where('slug', $slug)->whereHas('productGroup', fn (Builder $q) => $q->where('slug', $group->value))->exists();
    }

    public function productExistsForGroup(string $uuid, ProductGroupType $group): bool
    {
        return Product::where('uuid', $uuid)->whereHas('productGroup', fn (Builder $q) => $q->where('slug', $group->value))->exists();
    }

    /** @return Collection<int, Product> */
    public function getProductsWithMetaData(): Collection
    {
        return Product::query()
            ->orderBy('weight', SortDirection::Descending)
            ->with([
                'productGroup',
                'productSpecs',
            ])
            ->get();
    }

    public function getQuarantaineProduct(): ?Product
    {
        $productGroup = ProductGroup::where('slug', ProductGroupType::ONE_TIME_SERVICE)
            ->with(['products'])
            ->first();

        if ($productGroup === null || $productGroup->products->count() === 0) {
            return null;
        }

        return Product::where('slug', 'quarantainekosten')
            ->whereHas('productGroup', function (Builder $builder): void {
                $builder->where('slug', ProductGroupType::ONE_TIME_SERVICE);
            })->first();
    }

    public function hasPricesWithMultipleContractPeriods(Product $product): bool
    {
        $prices = ProductPriceComponent::where('product_id', $product->id)->get();

        return $prices->unique(fn (ProductPriceComponent $price) => $price->contract_period)->count() > 1;
    }

    /**
     * @return Collection<int, ProductAllowedChange>
     */
    public function getAllAllowedProductChangesFromProduct(int $productId): Collection
    {
        return ProductAllowedChange::where('from_product_id', $productId)->with(['toProduct'])->get();
    }

    /**
     * @param array<int, int> $productIds
     *
     * @return Collection<int, Product>
     */
    public function getProductsUsingIds(array $productIds): Collection
    {
        return Product::whereIn('id', $productIds)->with([
            'productGroup',
            'productSpecs',
        ])->get();
    }

    public function hasServicePlus(Product $product): bool
    {
        return Product::query()
            ->whereHas('productSpecs', function (Builder $query): void {
                $query
                    ->where('name', ProductSpecName::HAS_SERVICE_PLUS->value)
                    ->whereIn('value', [true, 'true', '1', 1, 'yes'])
                ;
            })
            ->where('id', $product->id)
            ->exists();
    }

    public function comesWithFreeProduct(Product $product): ?Product
    {
        $product->loadMissing(['productSpecs']);
        $spec = $product->productSpecs
            ->where('name', ProductSpecName::COMES_WITH_FREE_PRODUCT_SLUG->value)
            ->first();

        if ($spec instanceof ProductSpec) {
            return $this->findProductBySlug((string) $spec->value);
        }

        return null;
    }
}
