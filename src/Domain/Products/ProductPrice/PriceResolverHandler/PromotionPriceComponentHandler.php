<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\ProductPrice\PriceResolverHandler;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Waterfront\Domain\Pricing\DTO\PriceComponents\PromotionPriceComponent;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\DTO\Price as PriceDto;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Webmozart\Assert\Assert;

class PromotionPriceComponentHandler
{
    /**
     * @param Collection<int, PriceDto> $prices
     *
     * @return Collection<int, PriceDto>
     */
    public function handle(Collection $prices): Collection
    {
        $productIds = $prices->pluck('productId')->toArray();
        Assert::allInteger($productIds);
        $productIdsString = implode(',', array_map(fn ($id) => strval($id), $productIds));

        $promotions = DB::select(
            <<<SQL
            select distinct on (product_price_components.product_id, product_price_components.contract_period, product_price_components.billing_period) product_price_components.product_id, product_price_components.id, product_price_components.contract_period, product_price_components.billing_period, product_price_components.price
            from product_price_components
            where product_price_components.product_id in ({$productIdsString})
            and product_price_components.starts_at <= :currentDate
            and (product_price_components.expires_at is null or product_price_components.expires_at > :currentDate)
            and product_price_components.type = :priceType
            order by product_price_components.product_id, product_price_components.contract_period, product_price_components.billing_period, product_price_components.starts_at desc
            SQL,
            ['currentDate' => CarbonImmutable::now(), 'priceType' => PriceComponentType::PROMOTION->value],
        );

        foreach ($promotions as $promotion) {
            $price = $prices
                ->where('productId', $promotion->product_id)
                ->where('type', ProductPriceType::REGISTRATION)
                ->where('contractPeriod', $promotion->contract_period)
                ->where('billingPeriod', $promotion->billing_period)
                ->firstOrFail();

            $price->possiblePriceComponents[] = new PromotionPriceComponent(
                null,
                null,
                $promotion->price,
                $promotion->price,
            );
        }

        return $prices;
    }
}
