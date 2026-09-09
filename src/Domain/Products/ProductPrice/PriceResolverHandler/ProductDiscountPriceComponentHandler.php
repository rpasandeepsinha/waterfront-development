<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\ProductPrice\PriceResolverHandler;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProlongationStaffelPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\RegistrationStaffelPriceComponent;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Repositories\DiscountRepository;
use Webmozart\Assert\Assert;

readonly class ProductDiscountPriceComponentHandler
{
    public function __construct(
        private DiscountRepository $discountRepository,
    ) {
    }

    /**
     * Some customers own a "Discount product". While a customer owns such a product, some special prices may
     * apply to that customer. For example: "12 month Registration for .nl is 5eur", this will override the
     * default price for 12 month registrations. These overrides are applied and the updated price list is
     * returned.
     * In the case that a discount has already been applied to the price, it will be overwritten by the discounted
     * price from the database, and the previously applied discount will be gone.
     *
     * @param Collection<int, Price> $prices
     *
     * @return Collection<int, Price>
     */
    public function handle(Collection $prices, Customer $customer): Collection
    {
        if (! $this->discountRepository->hasProductDiscounts($customer)) {
            return $prices;
        }

        $productIds = $prices->pluck('productId')->toArray();
        Assert::allInteger($productIds);
        $productIdsString = implode(',', array_map(fn ($id) => strval($id), $productIds));

        $staffelPrices = DB::select(<<<SQL
            select distinct on (customer_product_discount.id, product_id, contract_period, billing_period, type) product_price_components.product_id, product_price_components.id, product_price_components.contract_period, product_price_components.billing_period, product_price_components.type, product_price_components.price
            from product_price_components
            join product_discount_prices on product_price_components.id = product_discount_prices.price_id
            join customer_product_discount on product_discount_prices.product_discount_id = customer_product_discount.product_discount_id
            where product_price_components.product_id in ({$productIdsString})
                and customer_product_discount.customer_id = :customerId
                and product_price_components.type in (:registrationStaffel, :prolongationStaffel)
                and product_price_components.starts_at <= :currentDate
                and (product_price_components.expires_at is null or product_price_components.expires_at > :currentDate)
            order by customer_product_discount.id, product_price_components.product_id, product_price_components.contract_period, product_price_components.billing_period, product_price_components.type, product_price_components.starts_at desc
            SQL, ['currentDate' => CarbonImmutable::now(), 'customerId' => $customer->id, 'registrationStaffel' => PriceComponentType::REGISTRATION_STAFFEL->value, 'prolongationStaffel' => PriceComponentType::PROLONGATION_STAFFEL->value]);

        foreach ($staffelPrices as $staffelPrice) {
            $price = $prices
                ->where('productId', $staffelPrice->product_id)
                ->where('type', ProductPriceType::REGISTRATION)
                ->where('contractPeriod', $staffelPrice->contract_period)
                ->where('billingPeriod', $staffelPrice->billing_period)
                ->firstOrFail();

            $type = PriceComponentType::from($staffelPrice->type);

            $priceComponent = match ($type) {
                PriceComponentType::REGISTRATION_STAFFEL => new RegistrationStaffelPriceComponent(null, null, $staffelPrice->price, $staffelPrice->price),
                PriceComponentType::PROLONGATION_STAFFEL => new ProlongationStaffelPriceComponent(null, null, $staffelPrice->price, $staffelPrice->price),
                default => throw new RuntimeException(),
            };

            $price->possiblePriceComponents[] = $priceComponent;
        }

        return $prices;
    }
}
