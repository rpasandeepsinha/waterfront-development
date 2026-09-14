<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\ProductPrice\PriceResolverHandler;

use Illuminate\Support\Collection;
use Waterfront\Domain\Pricing\DTO\PriceComponents\PriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\RegistrationPriceComponent;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\Enums\ProductPriceType;

class BasePriceHandler
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
            $prolongationPrices = $productPrices->where('type', ProductPriceType::PROLONGATION)->all();

            foreach ($prolongationPrices as $prolongationPrice) {
                $registrationPrice = $productPrices
                    ->where('type', ProductPriceType::REGISTRATION)
                    ->where('contractPeriod', $prolongationPrice->contractPeriod)
                    ->where('billingPeriod', $prolongationPrice->billingPeriod)
                    ->first();

                if (! $registrationPrice instanceof Price) {
                    $prices[] = new Price(
                        type: ProductPriceType::REGISTRATION,
                        billingPeriod: $prolongationPrice->billingPeriod,
                        productId: $prolongationPrice->productId,
                        productGroupUuid: $prolongationPrice->productGroupUuid,
                        regularPrice: $prolongationPrice->regularPrice,
                        contractPeriod: $prolongationPrice->contractPeriod,
                        orderable: $prolongationPrice->orderable,
                        is_default: false,
                    );
                }
            }
        }

        // Add possible priceComponents to entries that do not already have it. This could be:
        // A product price entry that doesnt exist in the prices table
        // A registration entry added by the fall-forward based on the prolongation price
        $prices
            ->filter(fn (Price $price) => $price->type === ProductPriceType::REGISTRATION)
            ->filter(
                fn (Price $price) => (
                    array_find(
                        $price->possiblePriceComponents,
                        fn (PriceComponent $priceComponent): bool => (
                            $priceComponent->type === PriceComponentType::REGISTRATION
                        ),
                    ) === null
                ),
            )
            ->each(
                fn (Price $price) => $price->possiblePriceComponents[] =
                    new RegistrationPriceComponent($price->regularPrice),
            );

        return $prices;
    }
}
