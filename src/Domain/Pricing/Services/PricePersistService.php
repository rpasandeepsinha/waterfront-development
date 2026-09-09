<?php

declare(strict_types=1);

namespace Waterfront\Domain\Pricing\Services;

use Carbon\CarbonImmutable;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Pricing\DTO\PriceComponents\CustomIndefinitePriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\CustomOneOffPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\IntroductionPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\PriceComponent;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\CustomPriceReason;
use Waterfront\Domain\Pricing\Models\OrderLinePrice;
use Waterfront\Domain\Pricing\Models\OrderLinePriceComponent;
use Waterfront\Domain\Pricing\Models\SubscriptionPrice;
use Waterfront\Domain\Pricing\Models\SubscriptionPriceComponent;
use Waterfront\Domain\Pricing\Repositories\PriceRepository;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\Enums\CustomPriceReasonType;
use Waterfront\Domain\Products\Services\PriceService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Webmozart\Assert\Assert;

readonly class PricePersistService
{
    public function __construct(
        private PriceRepository $priceRepository,
        private PriceService $priceService,
    ) {
    }

    public function persistSubscriptionPrice(Subscription $subscription, Price $price, CarbonImmutable $validFrom): void
    {
        Assert::natural($price->calculatedPrice);

        $subscriptionPrice = $this->createSubscriptionPrice($subscription, $price->appliedPriceComponents, $validFrom);

        $subscription->gross_price = $price->regularPrice;
        $subscription->net_price = $price->calculatedPrice;
        $subscription->subscription_price_id = $subscriptionPrice->id;
        $subscription->save();
    }

    public function persistOrderLineItemPrice(OrderLineItem $orderLine, Price $price): void
    {
        Assert::natural($price->calculatedPrice);

        $priceComponents = $price->appliedPriceComponents;
        $startBillingCycle = $orderLine->created_at ?? CarbonImmutable::now();

        $this->createOrderLinePrice($orderLine, $startBillingCycle, $priceComponents);

        $orderLine->gross_price = $price->regularPrice;
        $orderLine->net_price = $price->calculatedPrice;
        $orderLine->save();

        // When there is an introduction component with firstMonthsDiscountPeriod (i.e. first X months Y discount) we
        // calculate and save subscription prices for every billing cycle in advance. That's what the rest of the code
        // does.

        $introductionComponent = array_find(
            $priceComponents,
            fn (PriceComponent $component): bool => $component->type === PriceComponentType::INTRODUCTION
        );

        if ($introductionComponent instanceof IntroductionPriceComponent && $introductionComponent->firstMonthsDiscountPeriod !== null) {
            $billingCycle = 2;
            $totalBillingCycles = $orderLine->contract_period / $orderLine->billing_period;
            // firstMonthsDiscountPeriod can be lower than billing_period (example 12/12 subscription with first 3 months discount).
            // That should be rounded to 1, as it is still a discounted billing cycle.
            $discountedBillingCycles = ceil($introductionComponent->firstMonthsDiscountPeriod / $orderLine->billing_period);
            $normalBillingCycles = $totalBillingCycles - $discountedBillingCycles;

            // We are now in the second billing cycle, so we want to remove pro-rate components, because
            // they are only for the first billing cycle/invoice.
            $priceComponents = $this->withoutPriceComponent($priceComponents, PriceComponentType::PRO_RATE);

            for ($i = 1; $i < $discountedBillingCycles; $i++) {
                $this->createOrderLinePrice($orderLine, $startBillingCycle->addMonths(($billingCycle - 1) * $orderLine->billing_period), $priceComponents);

                $billingCycle++;
            }

            // We've passed the discounted period so we remove the introduction component and continue setting up prices
            // for the remaining billing cycles.
            $priceComponents = $this->withoutPriceComponent($priceComponents, PriceComponentType::INTRODUCTION);

            for ($i = 0; $i < $normalBillingCycles; $i++) {
                $this->createOrderLinePrice($orderLine, $startBillingCycle->addMonths(($billingCycle - 1) * $orderLine->billing_period), $priceComponents);

                $billingCycle++;
            }
        }
    }

    public function persistSubscriptionPriceFromOrderLine(Subscription $subscription, OrderLineItem $orderLine): void
    {
        $orderLine->loadMissing('prices.components');

        $firstSubscriptionPrice = null;

        foreach ($orderLine->prices as $orderLinePrice) {
            $subscriptionPrice = new SubscriptionPrice();
            $subscriptionPrice->subscription_id = $subscription->id;
            $subscriptionPrice->valid_from = $orderLinePrice->valid_from;
            $subscriptionPrice->net_price = $orderLinePrice->net_price;
            $subscriptionPrice->save();

            $firstSubscriptionPrice ??= $subscriptionPrice;

            foreach ($orderLinePrice->components as $component) {
                $subscriptionPriceComponent = new SubscriptionPriceComponent();
                $subscriptionPriceComponent->subscription_price_id = $subscriptionPrice->id;
                $subscriptionPriceComponent->type = $component->type;
                $subscriptionPriceComponent->percentage_discount = $component->percentage_discount;
                $subscriptionPriceComponent->fixed_discount = $component->fixed_discount;
                $subscriptionPriceComponent->fixed_price = $component->fixed_price;
                $subscriptionPriceComponent->new_price = $component->new_price;
                $subscriptionPriceComponent->order_applied = $component->order_applied;
                $subscriptionPriceComponent->save();
            }
        }

        assert($firstSubscriptionPrice instanceof SubscriptionPrice);

        /**
         * We save all price components with the orderline, which can include a pro-rate component. We SHOULD NOT save the resulting
         * calculated price directly on the subscription, because that would _also_ include the pro-rate component, which would then be
         * included with each invoice.
         *
         * Example: with a 12/1 subscription the pro-rate price should only be invoiced once during the first invoice. All subsequent
         * invoices simply take the subscription->net_price so that should _not_ include the pro-rate price component.
         *
         * Below we try to find a pro-rate component. If it exists we take the price of the component before it and save it on the subscription.
         * All subsequent invoices take the net_price of the subscription, which now contains the amount before pro-rate was applied.
         */
        $proRatePriceComponent = array_find(
            $firstSubscriptionPrice->components->all(),
            fn (SubscriptionPriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::PRO_RATE
        );

        if ($proRatePriceComponent !== null) {
            $priceComponentBeforeProRate = array_find(
                $firstSubscriptionPrice->components->all(),
                fn (SubscriptionPriceComponent $priceComponent): bool => $priceComponent->order_applied === $proRatePriceComponent->order_applied - 1
            );

            Assert::notNull($priceComponentBeforeProRate);

            $subscription->net_price = $priceComponentBeforeProRate->new_price;
            $subscription->save();
        }

        $subscription->subscription_price_id = $firstSubscriptionPrice->id;
        $subscription->save();
    }

    /**
     * @param int<0, max> $price
     *
     * Overriding a subscription price is essentially a breach of contract, because that price is used for each
     * invoice within the contract period. That price is different than what the customer agreed to when the
     * subscription is ordered.
     *
     * We might have already calculated all "billing cycles" (i.e. a subscription price for each invoice), so
     * those will be removed, and we mark the custom price override as such.
     */
    public function persistCustomPrice(Subscription $subscription, int $price, bool $oneOff, CustomPriceReasonType $reason): void
    {
        $this->priceRepository->deleteFutureSubscriptionPrices($subscription);

        if ($oneOff) {
            $priceComponent = new CustomOneOffPriceComponent($price);
        } else {
            $priceComponent = new CustomIndefinitePriceComponent($price);
        }

        $newSubscriptionPrice = $this->createSubscriptionPrice($subscription, [$priceComponent], CarbonImmutable::now());

        $customPriceReason = new CustomPriceReason();
        $customPriceReason->subscription_price_id = $newSubscriptionPrice->id;
        $customPriceReason->reason = $reason;
        $customPriceReason->save();

        $subscription->net_price = $price;
        $subscription->subscription_price_id = $newSubscriptionPrice->id;
        $subscription->save();
    }

    /**
     * @param array<PriceComponent> $components
     */
    private function createSubscriptionPrice(Subscription $subscription, array $components, CarbonImmutable $validFrom): SubscriptionPrice
    {
        $subscriptionPrice = new SubscriptionPrice();
        $subscriptionPrice->subscription_id = $subscription->id;
        $subscriptionPrice->valid_from = $validFrom;
        $subscriptionPrice->net_price = $this->getLastAppliedComponent($components)->newPrice;
        $subscriptionPrice->save();

        foreach ($components as $component) {
            Assert::notNull($component->appliedOrder);

            $priceComponent = new SubscriptionPriceComponent();
            $priceComponent->subscription_price_id = $subscriptionPrice->id;
            $priceComponent->type = $component->type;
            $priceComponent->percentage_discount = $component->percentageDiscount;
            $priceComponent->fixed_discount = $component->fixedDiscount;
            $priceComponent->fixed_price = $component->fixedPrice;
            $priceComponent->new_price = $component->newPrice;
            $priceComponent->order_applied = $component->appliedOrder;
            $priceComponent->save();
        }

        return $subscriptionPrice;
    }

    /**
     * @param array<PriceComponent> $components
     */
    private function createOrderLinePrice(OrderLineItem $orderLine, CarbonImmutable $from, array $components): OrderLinePrice
    {
        $orderLinePrice = new OrderLinePrice();
        $orderLinePrice->order_line_item_id = $orderLine->id;
        $orderLinePrice->valid_from = $from;
        $orderLinePrice->net_price = $this->getLastAppliedComponent($components)->newPrice;
        $orderLinePrice->save();

        foreach ($components as $component) {
            Assert::notNull($component->appliedOrder);

            $priceComponent = new OrderLinePriceComponent();
            $priceComponent->order_line_price_id = $orderLinePrice->id;
            $priceComponent->type = $component->type;
            $priceComponent->percentage_discount = $component->percentageDiscount;
            $priceComponent->fixed_discount = $component->fixedDiscount;
            $priceComponent->fixed_price = $component->fixedPrice;
            $priceComponent->new_price = $component->newPrice;
            $priceComponent->order_applied = $component->appliedOrder;
            $priceComponent->save();
        }

        return $orderLinePrice;
    }

    /**
     * When setting up pricing for multiple billing cycles you sometimes want to remove a certain price component
     * before assembling the next billing cycle. As an example you can think about something with a registration price
     * component and a pro-rate price component on top. This is fine for the first billing cycle, but for all
     * subsequent cycles the pro-rate component doesn't make sense and has to go.
     *
     * If there are any components after the one you are removing, they should be recalculated based on the one
     * before the removed one, if they contain a discount.
     *
     * @param array<PriceComponent> $components
     *
     * @return array<PriceComponent>
     */
    private function withoutPriceComponent(array $components, PriceComponentType $type): array
    {
        // Trying to remove the registration/base price component doesn't make sense.
        if ($type === PriceComponentType::REGISTRATION) {
            return $components;
        }

        $componentToRemoveKey = array_find_key(
            $components,
            fn (PriceComponent $component): bool => $component->type === $type
        );

        if ($componentToRemoveKey === null) {
            return $components;
        }

        $componentToRemoveAppliedOrder = $components[$componentToRemoveKey]->appliedOrder;
        Assert::notNull($componentToRemoveAppliedOrder);

        unset($components[$componentToRemoveKey]);

        // Walk the components in the order they were applied so each discounted component after the removed one can be
        // recalculated based on the running price of the component before it.
        usort(
            $components,
            fn (PriceComponent $a, PriceComponent $b): int => ($a->appliedOrder ?? 0) <=> ($b->appliedOrder ?? 0)
        );

        $basePrice = null;
        foreach ($components as $component) {
            if ($component->appliedOrder < $componentToRemoveAppliedOrder) {
                // Applied before the removed component, so it is unaffected. Its price is the base for the first
                // component that has to be recalculated.
                $basePrice = $component->newPrice;

                continue;
            }

            // Everything after the removed component shifts one place up.
            $component->appliedOrder = max(1, $component->appliedOrder - 1);

            // Only components that actually apply a discount are rebased. A component that sets an absolute price does
            // not depend on the price before it.
            if ($basePrice !== null && $component->fixedDiscount !== null) {
                $component->newPrice = max($basePrice - $component->fixedDiscount, 0);
            } elseif ($basePrice !== null && $component->percentageDiscount !== null) {
                $component->newPrice = $this->priceService->calculatePercentageDiscount(
                    $basePrice,
                    $component->percentageDiscount,
                );
            }

            $basePrice = $component->newPrice;
        }

        return $components;
    }

    /**
     * @param array<PriceComponent> $components
     */
    private function getLastAppliedComponent(array $components): PriceComponent
    {
        Assert::notEmpty($components);

        $lastAppliedComponent = array_first($components);

        foreach ($components as $component) {
            if ($lastAppliedComponent->appliedOrder < $component->appliedOrder) {
                $lastAppliedComponent = $component;
            }
        }

        return $lastAppliedComponent;
    }
}
