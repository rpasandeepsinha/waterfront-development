<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Products;

use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Pricing\Repositories\PriceRepository;
use Waterfront\Domain\Products\Models\Product;

class ProductPresenter
{
    /** @var list<string> */
    public const PRESENTED_RELATIONS = [
        'productGroup',
        'productSpecs',
        'allowedChanges',
        'productPromotions',
        'addonCouplings.addonProduct',
        'introductionDiscounts',
    ];

    public function __construct(
        private readonly PriceRepository $priceRepository,
        private readonly PriceComponentPresenter $priceComponentPresenter,
    ) {
    }

    /** @return array<mixed> */
    public function toArray(Product $product): array
    {
        $product->loadMissing(self::PRESENTED_RELATIONS);

        $prices = $this->priceRepository->getActivePrices($product->id);

        return [
            'id' => $product->id,
            'uuid' => $product->uuid,
            'slug' => $product->slug,
            'name' => $product->name,
            'orderable' => $product->orderable,
            'description' => $product->description,
            'group' => ProductGroupResource::make($product->productGroup),
            'specifications' => ProductSpecResource::collection($product->productSpecs),
            'allowedChange' => ProductAllowedChangesResource::collection($product->allowedChanges),
            'promotions' => ProductPromotionResource::collection($product->productPromotions),
            'prices' => array_map(fn (ProductPriceComponent $priceComponent) => $this->priceComponentPresenter->toArray(
                $priceComponent,
            ), $prices->all()),
            'addons' => ProductAddonResource::collection($product->addonCouplings),
            'introduction_price_configuration' => $product->introductionDiscounts->count() === 0
                ? []
                : ProductIntroductionDiscountResource::collection($product->introductionDiscounts),
        ];
    }
}
