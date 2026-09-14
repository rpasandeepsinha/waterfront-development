<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\ProductPrice\PriceResolverHandler;

use Illuminate\Support\Collection;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProlongationPriceComponent;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\Enums\ProductPriceType;

class ProlongationPriceComponentHandler
{
    /**
     * @param Collection<int, Price> $prices
     *
     * @return Collection<int, Price>
     */
    public function handle(Collection $prices): Collection
    {
        $groupedProductPrices = $prices->groupBy('productId');

        foreach ($groupedProductPrices as $productPrices) {
            /** @var Collection<int, Price> $productPrices */
            $registrationPrices = $productPrices->where('type', ProductPriceType::REGISTRATION)->all();

            foreach ($registrationPrices as $registrationPrice) {
                $prolongationPrice = $productPrices
                    ->where('type', ProductPriceType::PROLONGATION)
                    ->where('contractPeriod', $registrationPrice->contractPeriod)
                    ->where('billingPeriod', $registrationPrice->billingPeriod)
                    ->first();

                if ($prolongationPrice instanceof Price) {
                    $registrationPrice->possiblePriceComponents[] = new ProlongationPriceComponent(
                        null,
                        null,
                        $prolongationPrice->regularPrice,
                        $prolongationPrice->regularPrice,
                    );
                } else {
                    // We're only adding a new prolongation price here. As we use the same price as registration
                    // there is no need to add a priceComponent to the registration price
                    $prices[] = new Price(
                        type: ProductPriceType::PROLONGATION,
                        billingPeriod: $registrationPrice->billingPeriod,
                        productId: $registrationPrice->productId,
                        productGroupUuid: $registrationPrice->productGroupUuid,
                        regularPrice: $registrationPrice->regularPrice,
                        contractPeriod: $registrationPrice->contractPeriod,
                        orderable: $registrationPrice->orderable,
                        is_default: false,
                    );
                }
            }
        }

        return $prices;
    }
}
