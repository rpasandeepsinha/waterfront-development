<?php

declare(strict_types=1);

namespace Waterfront\Domain\OneTimeServices\Services;

use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;

class GrossPriceResolver
{
    public function __construct(
        private readonly PriceResolver $priceResolver,
    ) {
    }

    public function getGrossPrice(Product $oneTimeServiceProduct, int $alternativeProductId): int
    {
        $priceRequest = new PriceRequest([new RegistrationPriceRequest($oneTimeServiceProduct)], null);
        $servicePriceList = $this->priceResolver->getPriceList($priceRequest);

        $priceDto = $servicePriceList->getProductPrice(
            $oneTimeServiceProduct->slug,
            1,
            1,
        );

        return $priceDto->getAlternativeGrossPrice($alternativeProductId) ?? $priceDto->regularPrice;
    }
}
