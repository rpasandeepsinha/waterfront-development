<?php

declare(strict_types=1);

namespace Waterfront\Domain\Pricing\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductIntroductionDiscount;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Pricing\Models\SubscriptionPrice;
use Waterfront\Domain\Pricing\Models\SubscriptionPriceComponent;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class PriceRepository
{
    public function deleteFutureSubscriptionPrices(Subscription $subscription): void
    {
        SubscriptionPrice::where('subscription_id', $subscription->id)
            ->where('valid_from', '>', CarbonImmutable::now())
            ->delete();
    }

    public function hasIndefiniteCustomPrice(Subscription $subscription): bool
    {
        return (
            $subscription->activePrice?->components()->where('type', PriceComponentType::CUSTOM_INDEFINITE)->exists()
            ?? false
        );
    }

    /**
     * Checks if the given subscription has ever had a certain price component type. This also checks historical prices.
     */
    public function hasAppliedPriceComponent(Subscription $subscription, PriceComponentType $type): bool
    {
        return SubscriptionPriceComponent::query()
            ->whereIn('subscription_price_id', SubscriptionPrice::query()
                ->select('id')
                ->where('subscription_id', $subscription->id))
            ->where('type', $type)
            ->exists();
    }

    /**
     * @return Collection<int, ProductIntroductionDiscount>
     */
    public function getAllIntroductionDiscountsFromProduct(int $productId): Collection
    {
        return ProductIntroductionDiscount::where('product_id', $productId)->get();
    }

    /**
     * @return Collection<int, ProductPriceComponent>
     */
    public function getActivePrices(int $productId): Collection
    {
        $now = CarbonImmutable::now();

        $sql = <<<SQL
        select distinct on (product_price_components.product_id, product_price_components.type, product_price_components.contract_period, product_price_components.billing_period)
            product_price_components.*
        from product_price_components
        join products on product_price_components.product_id = products.id
        where product_price_components.product_id = ?
            and product_price_components.starts_at <= ?
            and (product_price_components.expires_at is null or product_price_components.expires_at > ?)
            and (product_price_components.type in (?, ?, ?, ?))
        order by product_price_components.product_id, product_price_components.type, product_price_components.contract_period, product_price_components.billing_period, product_price_components.starts_at desc
        SQL;

        return ProductPriceComponent::fromQuery($sql, [
            $productId,
            $now,
            $now,
            PriceComponentType::REGISTRATION->value,
            PriceComponentType::INTRODUCTION->value,
            PriceComponentType::PROMOTION->value,
            PriceComponentType::PROLONGATION->value,
        ]);
    }

    /**
     * @return Collection<int, ProductPriceComponent>
     */
    public function getPricesForProductDiscount(int $productDiscountId): Collection
    {
        $now = CarbonImmutable::now();

        $sql = <<<SQL
        select distinct on (product_price_components.product_id, product_price_components.type, product_price_components.contract_period, product_price_components.billing_period)
            product_price_components.*
        from product_price_components
        join products on product_price_components.product_id = products.id
        join product_discount_prices on product_discount_prices.price_id = product_price_components.id
        join product_discounts on product_discounts.id = product_discount_prices.product_discount_id
        where product_price_components.starts_at <= ?
            and (product_price_components.expires_at is null or product_price_components.expires_at > ?)
            and product_discounts.id = ?
        order by product_price_components.product_id, product_price_components.type, product_price_components.contract_period, product_price_components.billing_period, product_price_components.starts_at desc
        SQL;

        return ProductPriceComponent::query()->fromQuery($sql, [
            $now,
            $now,
            $productDiscountId,
        ]);
    }
}
