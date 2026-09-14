<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Products;

use Waterfront\Apps\API\Compass\Resources\Customer\CustomerResource;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Pricing\Repositories\PriceRepository;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductDiscount;

class ProductDiscountPresenter
{
    public function __construct(
        private readonly PriceRepository $priceRepository,
        private readonly PriceComponentPresenter $priceComponentPresenter,
        private readonly ProductPresenter $productPresenter,
    ) {
    }

    /** @return array<mixed> */
    public function toArray(ProductDiscount $productDiscount): array
    {
        $productDiscount->loadMissing('customers.migratedCustomers');

        $priceComponents = $this->priceRepository->getPricesForProductDiscount($productDiscount->id);
        $priceComponents->loadMissing(['product']);

        $prices = array_map(fn (ProductPriceComponent $priceComponent) => $this->priceComponentPresenter->toArray(
            $priceComponent,
        ), $priceComponents->values()->all());

        $products = $priceComponents
            ->unique('product_id')
            ->values()
            ->map(fn (ProductPriceComponent $priceComponent) => $priceComponent->product)
            ->loadMissing(ProductPresenter::PRESENTED_RELATIONS);

        return [
            'id' => $productDiscount->id,
            'name' => $productDiscount->name,
            'description' => $productDiscount->description ?? null,
            'customers' => CustomerResource::collection($productDiscount->customers),
            'productPrices' => $prices,
            'products' => array_map(
                fn (Product $product) => $this->productPresenter->toArray($product),
                $products->all(),
            ),
        ];
    }
}
