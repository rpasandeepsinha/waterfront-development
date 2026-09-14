<?php

declare(strict_types=1);

namespace Waterfront\Domain\Pricing\Services;

use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Experiment\Models\Experiment;
use Waterfront\Domain\Pricing\DTO\PriceComponents\PriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceExperimentDTO;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;

class PriceExperimentService
{
    public function __construct(
        private readonly PriceResolver $priceResolver,
    ) {
    }

    /**
     * @return PriceExperimentDTO[]
     */
    public function getExperiments(): array
    {
        $allExperiments = Experiment::all();
        $productPriceRequests = [];
        $experiments = [];

        foreach ($allExperiments as $experiment) {
            foreach ($experiment->products as $product) {
                $productPriceRequests[] = new RegistrationPriceRequest(
                    $product,
                    experimentSlug: $experiment->slug->value,
                );
            }
        }

        $experimentPrices = $this->priceResolver->getPriceList(new PriceRequest($productPriceRequests, null));

        foreach ($allExperiments as $experiment) {
            foreach ($experiment->products as $product) {
                $productPrices = $experimentPrices->where('slug', $product->slug)->getPrices();
                $productPricesWithExperimentComponent = array_filter($productPrices, fn (Price $price) => array_any(
                    $price->appliedPriceComponents,
                    fn (PriceComponent $priceComponent) => (
                        $priceComponent->type === PriceComponentType::EXPERIMENT_PRICE_LADDER
                    ),
                ));

                if (count($productPricesWithExperimentComponent) > 0) {
                    $experiments[] = new PriceExperimentDTO(
                        Uuid::fromString($product->uuid),
                        $product->slug,
                        $experiment->slug,
                        $productPricesWithExperimentComponent,
                    );
                }
            }
        }

        return $experiments;
    }
}
