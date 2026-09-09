<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Products;

use Waterfront\Apps\API\Compass\Resources\Customer\CustomerResource;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Pricing\Repositories\PriceRepository;
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
        $priceComponents = $this->priceRepository->getPricesForProductDiscount($productDiscount->id);
        $prices = count($priceComponents) > 0 ? array_map(fn (ProductPriceComponent $priceComponent) => $this->priceComponentPresenter->toArray($priceComponent), $priceComponents->all()) : null;
        $products = array_map(fn (ProductPriceComponent $priceComponent) => $this->productPresenter->toArray($priceComponent->product), $priceComponents->unique('product_id')->all());

        return [
            'id' => $productDiscount->id,
            'name' => $productDiscount->name,
            'description' => $productDiscount->description ?? null,
            'customers' => CustomerResource::collection($productDiscount->customers),
            'productPrices' => $prices,
            'products' => $products,
        ];
    }
}
