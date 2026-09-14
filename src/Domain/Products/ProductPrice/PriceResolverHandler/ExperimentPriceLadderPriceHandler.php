<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\ProductPrice\PriceResolverHandler;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Waterfront\Domain\Experiment\Repositories\ExperimentRepository;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ExperimentPriceLadderPriceComponent;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\ProductPriceRequest;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\Enums\ProductPriceType;

readonly class ExperimentPriceLadderPriceHandler
{
    public function __construct(
        private ExperimentRepository $experimentRepository,
    ) {
    }

    /**
     * @param Collection<int, Price>          $prices
     * @param array<int, ProductPriceRequest> $productMap
     *
     * @return Collection<int, Price>
     */
    public function handle(Collection $prices, array $productMap): Collection
    {
        $slugsByProductId = [];
        foreach ($productMap as $productId => $request) {
            if (! $request instanceof ProlongationPriceRequest && ! $request instanceof RegistrationPriceRequest) {
                continue;
            }

            if ($request->experimentSlug !== null) {
                $slugsByProductId[$productId] = $request->experimentSlug;
            }
        }

        $productIds = $this->experimentRepository->filterProductsCoveredByExperiment($slugsByProductId);

        if ($productIds === []) {
            return $prices;
        }

        $productIdsString = implode(',', array_map(fn (int $id): string => strval($id), $productIds));

        $experimentPrices = DB::select(
            <<<SQL
            select distinct on (product_price_components.product_id, product_price_components.type, product_price_components.contract_period, product_price_components.billing_period) product_price_components.product_id, product_price_components.id, product_price_components.type, product_price_components.contract_period, product_price_components.billing_period, product_price_components.price
            from product_price_components
            where product_price_components.product_id in ({$productIdsString})
            and product_price_components.starts_at <= :currentDate
            and (product_price_components.expires_at is null or product_price_components.expires_at > :currentDate)
            and product_price_components.type in (:priceLadderType)
            order by product_price_components.product_id, product_price_components.type, product_price_components.contract_period, product_price_components.billing_period, product_price_components.starts_at desc
            SQL,
            [
                'currentDate' => CarbonImmutable::now(),
                'priceLadderType' => PriceComponentType::EXPERIMENT_PRICE_LADDER->value,
            ],
        );

        foreach ($experimentPrices as $experimentPrice) {
            $applicablePrices = $prices
                ->where('productId', $experimentPrice->product_id)
                ->whereIn('type', [ProductPriceType::PROLONGATION, ProductPriceType::REGISTRATION])
                ->where('contractPeriod', $experimentPrice->contract_period)
                ->where('billingPeriod', $experimentPrice->billing_period)
                ->all();

            foreach ($applicablePrices as $price) {
                $price->possiblePriceComponents[] = new ExperimentPriceLadderPriceComponent($experimentPrice->price);
            }
        }

        return $prices;
    }
}
