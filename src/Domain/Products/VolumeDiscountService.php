<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent as PriceModel;
use Waterfront\Domain\Pricing\Services\PricePersistService;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductDiscount;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Webmozart\Assert\Assert;

class VolumeDiscountService
{
    public function __construct(private readonly PriceResolver $priceResolver, private readonly PricePersistService $pricePersistService)
    {
    }

    public function attach(Customer $customer, ProductDiscount $productDiscount, Product $product, int $period): void
    {
        $productPrice = $this->getProductPrice($product, $customer, $period);
        Assert::natural($productPrice->calculatedPrice);

        $subscription = new Subscription();
        $subscription->product_uuid = $product->uuid;
        $subscription->customer_id = $customer->id;
        $subscription->domain = null;
        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $subscription->billing_period = $period;
        $subscription->contract_period = $period;
        $subscription->gross_price = $productPrice->regularPrice;
        $subscription->net_price = $productPrice->calculatedPrice;
        $subscription->cancel_date = null;
        $subscription->start_date = CarbonImmutable::now();
        $subscription->end_date = CarbonImmutable::now()->addMonths($period);
        $subscription->next_billing_date = CarbonImmutable::now()->addMonths($period);
        $subscription->parent_subscription_id = null;
        $subscription->save();

        $this->pricePersistService->persistSubscriptionPrice($subscription, $productPrice, $subscription->start_date);

        $customer->productDiscounts()->attach($productDiscount->id);
    }

    public function detach(Customer $customer, Product $product): void
    {
        foreach ($customer->productDiscounts as $discount) {
            if ($discount->product->uuid === $product->uuid) {
                $customer->productDiscounts()->detach($discount->id);
            }
        }
    }

    public function attachPrice(ProductDiscount|int $productDiscount, PriceModel|int $price): void
    {
        $productDiscountId = is_int($productDiscount) ? $productDiscount : $productDiscount->id;
        $priceId = is_int($price) ? $price : $price->id;

        DB::table('product_discount_prices')->insert([
            'product_discount_id' => $productDiscountId,
            'price_id' => $priceId,
        ]);
    }

    private function getProductPrice(Product $product, Customer $customer, int $period): Price
    {
        $priceRequest = new PriceRequest([new RegistrationPriceRequest($product)], $customer);
        $priceList = $this->priceResolver->getPriceList($priceRequest);

        return $priceList->getProductPrice($product->slug, $period, $period);
    }
}
