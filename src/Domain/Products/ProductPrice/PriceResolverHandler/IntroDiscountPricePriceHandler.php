<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\ProductPrice\PriceResolverHandler;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Orders\DTO\OrderCountDTO;
use Waterfront\Domain\Orders\Repositories\OrderRepository;
use Waterfront\Domain\Pricing\DTO\PriceComponents\IntroductionPriceComponent;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductIntroductionDiscount;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Webmozart\Assert\Assert;

readonly class IntroDiscountPricePriceHandler
{
    public function __construct(
        private OrderRepository $orderRepository,
    ) {
    }

    /**
     * @param Collection<int, Price> $prices
     *
     * @return Collection<int, Price>
     */
    public function handle(Collection $prices, ?Customer $customer): Collection
    {
        $productIds = $prices->pluck('productId')->toArray();
        Assert::allInteger($productIds);
        $productIdsString = implode(',', array_map(fn ($id) => strval($id), $productIds));

        $introductionDiscountsMap = $this->getAllIntroductionDiscounts($productIds);

        if (count($introductionDiscountsMap) === 0) {
            return $prices;
        }

        $orderCounts = new Collection();
        if ($customer !== null) {
            $orderCounts = $this->orderRepository->getCustomerOrderCountsByProductAndContractPeriod($customer);
        }

        $introductionPrices = DB::select(<<<SQL
            select distinct on (product_price_components.product_id, product_price_components.contract_period, product_price_components.billing_period) product_price_components.product_id, product_price_components.id, product_price_components.contract_period, product_price_components.billing_period, product_price_components.price
            from product_price_components
            where product_price_components.product_id in ({$productIdsString})
            and product_price_components.starts_at <= :currentDate
            and (product_price_components.expires_at is null or product_price_components.expires_at > :currentDate)
            and product_price_components.type = :priceType
            order by product_price_components.product_id, product_price_components.contract_period, product_price_components.billing_period, product_price_components.starts_at desc
            SQL, ['currentDate' => CarbonImmutable::now(), 'priceType' => PriceComponentType::INTRODUCTION->value]);

        foreach ($introductionPrices as $introductionPrice) {
            $registrationPrice = $prices
                ->where('productId', $introductionPrice->product_id)
                ->where('type', ProductPriceType::REGISTRATION)
                ->where('contractPeriod', $introductionPrice->contract_period)
                ->where('billingPeriod', $introductionPrice->billing_period)
                ->first();

            if ($registrationPrice === null) {
                continue;
            }

            if (! array_key_exists($introductionPrice->product_id, $introductionDiscountsMap)) {
                continue;
            }

            if (! array_key_exists($introductionPrice->contract_period, $introductionDiscountsMap[$introductionPrice->product_id])) {
                continue;
            }

            $firstMonthsDiscountPeriod = $introductionDiscountsMap[$introductionPrice->product_id][$introductionPrice->contract_period]['firstMonthsDiscountPeriod'];
            $maxUsesPerCustomer = $introductionDiscountsMap[$introductionPrice->product_id][$introductionPrice->contract_period]['maxUsesPerCustomer'];

            $orderCount = $orderCounts->first(
                fn (OrderCountDTO $count) =>
                $count->productId === $introductionPrice->product_id && $count->contractPeriod === $introductionPrice->contract_period
            );

            if ($orderCount === null || $maxUsesPerCustomer === null) {
                $remainingUses = $maxUsesPerCustomer;
            } else {
                $remainingUses = max(0, $maxUsesPerCustomer - $orderCount->count);
            }

            $registrationPrice->possiblePriceComponents[] = new IntroductionPriceComponent(null, null, $introductionPrice->price, $introductionPrice->price, $remainingUses, $maxUsesPerCustomer, $firstMonthsDiscountPeriod);
        }

        return $prices;
    }

    /**
     * @param int[] $productIds
     *
     * @return array<int, array<int, array{'maxUsesPerCustomer': non-negative-int|null, 'firstMonthsDiscountPeriod': int|null}>>
     */
    public function getAllIntroductionDiscounts(array $productIds): array
    {
        // Yes we query all entries here. There are a couple of products with discount so right now adding a where is not needed.
        $allIntroductionDiscountProducts = ProductIntroductionDiscount::all();

        $introductionDiscountsMap = [];

        foreach ($allIntroductionDiscountProducts as $allIntroductionDiscountProduct) {
            if (! in_array($allIntroductionDiscountProduct->product_id, $productIds, true)) {
                continue;
            }

            $introductionDiscountsMap[$allIntroductionDiscountProduct->product_id][$allIntroductionDiscountProduct->contract_period] = [
                'maxUsesPerCustomer' => $allIntroductionDiscountProduct->max_uses_per_customer,
                'firstMonthsDiscountPeriod' => $allIntroductionDiscountProduct->first_months_discount_period,
            ];
        }
        return $introductionDiscountsMap;
    }
}
