<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductPeriod;

class ProductPriceGenerator
{
    /**
     * @return Collection<int, ProductPriceComponent>
     */
    public static function generateStandardPrices(Product $product, int $monthPrice, bool $discounts = true): Collection
    {
        $prices = new Collection();

        foreach ([1, 12, 24, 36] as $contractPeriodIndex => $contractPeriod) {
            foreach ([1, 12, 24, 36] as $billingPeriod) {
                foreach ([PriceComponentType::REGISTRATION, PriceComponentType::PROLONGATION] as $type) {
                    if ($contractPeriod < $billingPeriod) {
                        continue;
                    }

                    if (($contractPeriod % $billingPeriod) !== 0) {
                        continue;
                    }

                    // Multi-year discount: for every year beyond the first that
                    // you sign up for, the monthly price decreases by 1 euro
                    $regularMonthPrice = $discounts ? $monthPrice - ((2 - $contractPeriodIndex) * 100) : $monthPrice;
                    /** @var int<0, max> $regularPrice */
                    $regularPrice = $regularMonthPrice * $billingPeriod;

                    $productPrice = new ProductPriceComponent();
                    $productPrice->type = $type;
                    $productPrice->product_id = $product->id;
                    $productPrice->contract_period = $contractPeriod;
                    $productPrice->billing_period = $billingPeriod;
                    $productPrice->price = $regularPrice;
                    $productPrice->orderable = true;
                    $productPrice->starts_at = CarbonImmutable::now();
                    $productPrice->save();

                    if ($type === PriceComponentType::REGISTRATION && $billingPeriod > 1 && $discounts) {
                        // Yearly prices: first 6 months for 99 cents (for testing purposes)
                        /** @var int<0, max> $promotionPrice */
                        $promotionPrice = ($regularMonthPrice * ($billingPeriod - 6)) + 594;

                        $productPeriod = new ProductPeriod();
                        $productPeriod->product_id = $product->id;
                        $productPeriod->contract_period = $contractPeriod;
                        $productPeriod->billing_period = $billingPeriod;
                        $productPeriod->action_period = 6;
                        $productPeriod->action_period_price = 99;
                        $productPeriod->save();

                        $price = new ProductPriceComponent();
                        $price->type = PriceComponentType::PROMOTION;
                        $price->product_id = $product->id;
                        $price->contract_period = $contractPeriod;
                        $price->billing_period = $billingPeriod;
                        $price->price = $promotionPrice;
                        $price->orderable = true;
                        $price->starts_at = CarbonImmutable::now();
                        $price->save();
                    }

                    $prices->push($productPrice);
                }
            }
        }

        return $prices;
    }
}
