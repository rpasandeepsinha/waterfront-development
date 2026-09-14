<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Atlantis\Resources\Products;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Pricing\DTO\PriceExperimentDTO;
use Waterfront\Domain\Products\DTO\PriceList;
use Waterfront\Domain\Products\Models\HostingProductComposition;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Models\ProductPromotion;

class PriceListResource extends JsonResource
{
    public static $wrap;

    /**
     * @param Collection<int, ProductGroup>              $productGroups
     * @param Collection<int, ProductPromotion>          $productPromotions
     * @param Collection<int, HostingProductComposition> $hostingProductCompositions
     * @param PriceExperimentDTO[]                       $experiments
     */
    public function __construct(
        public readonly PriceList $priceList,
        public readonly Collection $productGroups,
        public readonly Collection $productPromotions,
        public readonly Collection $hostingProductCompositions,
        public readonly int $defaultTax,
        public readonly array $experiments,
    ) {
        parent::__construct($priceList);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $groupedExperiments = [];
        $experiments = [];
        foreach ($this->experiments as $experiment) {
            $groupedExperiments[$experiment->experimentType->value][] = $experiment;
        }

        foreach ($groupedExperiments as $experimentSlug => $experimentsPerSlug) {
            $groupedPrices = [];
            foreach ($experimentsPerSlug as $experimentWithPrices) {
                $productUuid = $experimentWithPrices->productUuid->toString();
                if (! array_key_exists($productUuid, $groupedPrices)) {
                    $groupedPrices[$productUuid] = [];
                }

                $groupedPrices[$productUuid] = array_merge($groupedPrices[$productUuid], $experimentWithPrices->prices);
            }

            foreach ($groupedPrices as $productUuid => $prices) {
                $groupedPrices[$productUuid] = PriceResource::collection($prices);
            }

            $experiments[] = [
                'slug' => $experimentSlug,
                'prices' => $groupedPrices,
            ];
        }

        return [
            'meta' => [
                'tax' => $this->defaultTax,
            ],
            'products' => ProductResource::collection($this->priceList->all()),
            'productPromotions' => ProductPromotionResource::collection($this->productPromotions),
            'productGroups' => ProductGroupResource::collection($this->productGroups),
            'hostingProductCompositions' => HostingProductCompositionResource::collection($this->hostingProductCompositions),
            'experiments' => $experiments,
        ];
    }
}
