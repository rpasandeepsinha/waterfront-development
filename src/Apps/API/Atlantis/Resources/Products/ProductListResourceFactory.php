<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Atlantis\Resources\Products;

use Illuminate\Database\Eloquent\Collection;
use Waterfront\Domain\Pricing\DTO\PriceExperimentDTO;
use Waterfront\Domain\Products\DTO\PriceList;
use Waterfront\Domain\Products\Models\HostingProductComposition;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Models\ProductPromotion;
use Waterfront\Support\Config\ApplicationConfig;

readonly class ProductListResourceFactory
{
    public function __construct(
        private ApplicationConfig $config,
    ) {
    }

    /**
     * @param Collection<int, ProductGroup>              $productGroups
     * @param Collection<int, ProductPromotion>          $productPromotions
     * @param Collection<int, HostingProductComposition> $hostingProductCompositions
     * @param PriceExperimentDTO[]                       $experiments
     */
    public function makeResource(
        PriceList $priceList,
        Collection $productGroups,
        Collection $productPromotions,
        Collection $hostingProductCompositions,
        array $experiments,
    ): PriceListResource {
        return new PriceListResource(
            $priceList,
            $productGroups,
            $productPromotions,
            $hostingProductCompositions,
            $this->config->defaultTaxRate,
            $experiments,
        );
    }
}
