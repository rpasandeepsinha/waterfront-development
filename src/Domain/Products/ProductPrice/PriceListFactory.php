<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\ProductPrice;

use Illuminate\Support\Collection;
use Waterfront\Apps\API\Atlantis\Resources\Products\PriceResource;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\PriceList;
use Waterfront\Domain\Products\DTO\Product;
use Waterfront\Domain\Products\DTO\SpecificationMap;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Models\Product as ProductModel;
use Waterfront\Domain\Products\Models\ProductSpec;

class PriceListFactory
{
    /**
     * This factory only orders and formats data. There should not be ANY business logic in this class. Any price
     * calculations must be done by other components.
     *
     * @param Collection<int,ProductModel> $products
     * @param Collection<int, Price>       $prices
     *
     * @return PriceList<int, Product>
     */
    public function fromProductsAndPrices(Collection $products, Collection $prices): PriceList
    {
        $priceMap = [];
        foreach ($prices as $price) {
            $priceMap[$price->productId] ??= new Collection();
            $priceMap[$price->productId]->push($price);
        }

        return new PriceList($products->unique(fn (ProductModel $product) => $product->slug)->map(fn (ProductModel $product): Product => $this->productFromModel(
            product: $product,
            prices: $priceMap[$product->id] ?? new Collection(),
            specifications: $this->getSpecificationMap($product),
        )));
    }

    private function getSpecificationMap(ProductModel $product): SpecificationMap
    {
        /** @var array<string, string> $specifications */
        $specifications = $product->productSpecs->mapWithKeys(fn (ProductSpec $specification, int $key) => [
            $specification->name => $specification->value,
        ])->toArray();

        return new SpecificationMap($specifications);
    }

    /**
     * @param Collection<int, Price> $prices
     */
    private function productFromModel(
        ProductModel $product,
        Collection $prices,
        ?SpecificationMap $specifications = null,
    ): Product {
        $default_price = $this->getDefaultPriceForProduct($prices);

        return new Product(
            uuid: $product->uuid,
            type: $product->productGroup->slug,
            name: $product->name,
            slug: $product->slug,
            description: $product->description ?? '',
            weight: $product->weight,
            orderable: $product->orderable,
            prices: $prices,
            specifications: $specifications ?? new SpecificationMap([]),
            default_price: $default_price !== null ? PriceResource::make($default_price) : null,
        );
    }

    /**
     * @param Collection<int, Price> $prices
     */
    private function getDefaultPriceForProduct(Collection $prices): Price|null
    {
        return $prices->where('type', ProductPriceType::REGISTRATION)->first(fn (Price $price) => $price->is_default);
    }
}
