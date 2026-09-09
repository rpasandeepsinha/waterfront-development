<?php

declare(strict_types=1);

namespace Tests\Domain\Pricing\Services;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\VoucherFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Pricing\DTO\PriceComponents\IntroductionPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProductGroupPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProlongationPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProRatePriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\RegistrationPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\VoucherPriceComponent;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\OrderLinePrice;
use Waterfront\Domain\Pricing\Models\SubscriptionPrice;
use Waterfront\Domain\Pricing\Models\SubscriptionPriceComponent;
use Waterfront\Domain\Pricing\Services\PricePersistService;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\Enums\CustomPriceReasonType;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Models\Product;

#[CoversClass(PricePersistService::class)]
class PricePersistServiceTest extends IntegrationTestCase
{
    private PricePersistService $service;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = self::resolve(PricePersistService::class);
        $this->product = new ProductFactory()->for(new ProductGroupFactory())->createOne();
    }

    #[Test]
    public function persistPriceForSubscription(): void
    {
        $subscription = new SubscriptionFactory()->for(new CustomerFactory()->createOne())->for($this->product)->createOne();
        new ProductPriceComponentFactory()->for($this->product)->createOne(['type' => PriceComponentType::PROLONGATION, 'price' => 450]);
        $price = new Price(
            type: ProductPriceType::REGISTRATION,
            billingPeriod: 12,
            productId: $this->product->id,
            productGroupUuid: $this->product->productGroup->uuid,
            regularPrice: 450,
            contractPeriod: 12,
            orderable: true,
            is_default: true,
        );
        $price->appliedPriceComponents = [
            new ProlongationPriceComponent(fixedDiscount: null, percentageDiscount: null, fixedPrice: 450, newPrice: 450, appliedOrder: 1),
            new ProductGroupPriceComponent(fixedDiscount: null, percentageDiscount: 30, fixedPrice: null, newPrice: 315, appliedOrder: 2),
        ];
        $price->calculatedPrice = 315;

        $this->service->persistSubscriptionPrice($subscription, $price, $subscription->start_date);

        self::assertSame(450, $subscription->gross_price);
        self::assertSame(315, $subscription->net_price);
        self::assertSame(315, $subscription->activePrice?->net_price);

        self::assertDatabaseHas(
            'subscription_prices',
            [
                'subscription_id' => $subscription->id,
                'net_price' => 315,
                'valid_from' => $subscription->start_date,
            ]
        );
        self::assertDatabaseHas(
            'subscription_price_components',
            [
                'subscription_price_id' => $subscription->activePrice->id,
                'type' => PriceComponentType::PROLONGATION,
                'fixed_price' => 450,
                'new_price' => 450,
                'order_applied' => 1,
            ]
        );
        self::assertDatabaseHas(
            'subscription_price_components',
            [
                'subscription_price_id' => $subscription->activePrice->id,
                'type' => PriceComponentType::PRODUCT_GROUP,
                'percentage_discount' => 30,
                'new_price' => 315,
                'order_applied' => 2,
            ]
        );
    }

    #[Test]
    public function persistPriceWithIntroductionComponentForOrderLine(): void
    {
        $orderLine = new OrderLineItemFactory()->for(new OrderFactory()->for(new CustomerFactory()))->for($this->product)->createOne(['contract_period' => 3, 'billing_period' => 1]);
        $price = new Price(
            type: ProductPriceType::REGISTRATION,
            billingPeriod: 1,
            productId: $this->product->id,
            productGroupUuid: $this->product->productGroup->uuid,
            regularPrice: 450,
            contractPeriod: 3,
            orderable: true,
            is_default: true,
            calculatedPrice: 200
        );
        $price->appliedPriceComponents = [
            new RegistrationPriceComponent(price: 450),
            new IntroductionPriceComponent(fixedDiscount: null, percentageDiscount: null, fixedPrice: 200, newPrice: 200, remainingUses: 1, maxUsesPerCustomer: 1, firstMonthsDiscountPeriod: 2, appliedOrder: 2),
        ];

        $this->service->persistOrderLineItemPrice($orderLine, $price);

        self::assertSame(450, $orderLine->gross_price);
        self::assertSame(200, $orderLine->net_price);

        $prices = $orderLine->prices->sortBy('valid_from')->values();

        $price1 = $prices->shift();
        $price2 = $prices->shift();
        $price3 = $prices->shift();

        self::assertInstanceOf(OrderLinePrice::class, $price1);
        self::assertInstanceOf(OrderLinePrice::class, $price2);
        self::assertInstanceOf(OrderLinePrice::class, $price3);

        self::assertDatabaseHas(
            'order_line_price_components',
            [
                'order_line_price_id' => $price1->id,
                'type' => PriceComponentType::REGISTRATION,
                'fixed_price' => 450,
                'new_price' => 450,
                'order_applied' => 1,
            ]
        );
        self::assertDatabaseHas(
            'order_line_price_components',
            [
                'order_line_price_id' => $price1->id,
                'type' => PriceComponentType::INTRODUCTION,
                'fixed_price' => 200,
                'new_price' => 200,
                'order_applied' => 2,
            ]
        );
        self::assertDatabaseHas(
            'order_line_price_components',
            [
                'order_line_price_id' => $price2->id,
                'type' => PriceComponentType::REGISTRATION,
                'fixed_price' => 450,
                'new_price' => 450,
                'order_applied' => 1,
            ]
        );
        self::assertDatabaseHas(
            'order_line_price_components',
            [
                'order_line_price_id' => $price2->id,
                'type' => PriceComponentType::INTRODUCTION,
                'fixed_price' => 200,
                'new_price' => 200,
                'order_applied' => 2,
            ]
        );
        self::assertDatabaseHas(
            'order_line_price_components',
            [
                'order_line_price_id' => $price3->id,
                'type' => PriceComponentType::REGISTRATION,
                'fixed_price' => 450,
                'new_price' => 450,
                'order_applied' => 1,
            ]
        );
        self::assertDatabaseMissing(
            'order_line_price_components',
            [
                'order_line_price_id' => $price3->id,
                'type' => PriceComponentType::INTRODUCTION,
            ]
        );
    }

    #[Test]
    public function persistingPriceWithIntroductionComponentForOrderLineRemovesProRateComponentAfterFirstBillingCycle(): void
    {
        $orderLine = new OrderLineItemFactory()->for(new OrderFactory()->for(new CustomerFactory()))->for($this->product)->createOne(['contract_period' => 3, 'billing_period' => 1]);
        $price = new Price(
            type: ProductPriceType::REGISTRATION,
            billingPeriod: 1,
            productId: $this->product->id,
            productGroupUuid: $this->product->productGroup->uuid,
            regularPrice: 450,
            contractPeriod: 3,
            orderable: true,
            is_default: true,
            calculatedPrice: 190
        );

        // A prorate component is made and is later applied over other components, so the discount (read: price reduction) is only added later, and not passed into the constructor.
        $proRateComponent = new ProRatePriceComponent(until: CarbonImmutable::now()->addMonth(), amountPaid: 10, newPrice: 190, appliedOrder: 3);
        $proRateComponent->fixedDiscount = 10;
        $price->appliedPriceComponents = [
            new RegistrationPriceComponent(price: 450),
            new IntroductionPriceComponent(fixedDiscount: null, percentageDiscount: null, fixedPrice: 200, newPrice: 200, remainingUses: 1, maxUsesPerCustomer: 1, firstMonthsDiscountPeriod: 2, appliedOrder: 2),
            $proRateComponent,
        ];

        $this->service->persistOrderLineItemPrice($orderLine, $price);

        self::assertSame(450, $orderLine->gross_price);
        self::assertSame(190, $orderLine->net_price);

        $prices = $orderLine->prices->sortBy('valid_from')->values();

        $price1 = $prices->shift();
        $price2 = $prices->shift();
        $price3 = $prices->shift();

        self::assertInstanceOf(OrderLinePrice::class, $price1);
        self::assertInstanceOf(OrderLinePrice::class, $price2);
        self::assertInstanceOf(OrderLinePrice::class, $price3);

        self::assertDatabaseHas(
            'order_line_price_components',
            [
                'order_line_price_id' => $price1->id,
                'type' => PriceComponentType::REGISTRATION,
                'fixed_price' => 450,
                'new_price' => 450,
                'order_applied' => 1,
            ]
        );
        self::assertDatabaseHas(
            'order_line_price_components',
            [
                'order_line_price_id' => $price1->id,
                'type' => PriceComponentType::INTRODUCTION,
                'fixed_price' => 200,
                'new_price' => 200,
                'order_applied' => 2,
            ]
        );
        self::assertDatabaseHas(
            'order_line_price_components',
            [
                'order_line_price_id' => $price1->id,
                'type' => PriceComponentType::PRO_RATE,
                'fixed_discount' => 10,
                'new_price' => 190,
                'order_applied' => 3,
            ]
        );
        self::assertDatabaseHas(
            'order_line_price_components',
            [
                'order_line_price_id' => $price2->id,
                'type' => PriceComponentType::REGISTRATION,
                'fixed_price' => 450,
                'new_price' => 450,
                'order_applied' => 1,
            ]
        );
        self::assertDatabaseHas(
            'order_line_price_components',
            [
                'order_line_price_id' => $price2->id,
                'type' => PriceComponentType::INTRODUCTION,
                'fixed_price' => 200,
                'new_price' => 200,
                'order_applied' => 2,
            ]
        );
        self::assertDatabaseMissing(
            'order_line_price_components',
            [
                'order_line_price_id' => $price2->id,
                'type' => PriceComponentType::PRO_RATE,
            ]
        );
        self::assertDatabaseHas(
            'order_line_price_components',
            [
                'order_line_price_id' => $price3->id,
                'type' => PriceComponentType::REGISTRATION,
                'fixed_price' => 450,
                'new_price' => 450,
                'order_applied' => 1,
            ]
        );
        self::assertDatabaseMissing(
            'order_line_price_components',
            [
                'order_line_price_id' => $price3->id,
                'type' => PriceComponentType::INTRODUCTION,
            ]
        );
        self::assertDatabaseMissing(
            'order_line_price_components',
            [
                'order_line_price_id' => $price3->id,
                'type' => PriceComponentType::PRO_RATE,
            ]
        );
    }

    #[Test]
    public function persistingPriceWithIntroductionComponentForOrderLineCorrectlyRebasesComponentsWhenIntroductionIsRemoved(): void
    {
        // When you have a stack of three components, and remove the second one, the third one should be recalculated
        // based on the first one.
        //
        // Example with 3/1 subscription with 2 month introduction: registration 100, introduction -10%, voucher -20%.
        // At month 3 introduction should be removed and the voucher should be recalculated/rebased over the
        // registration price to get the price of the subscription for the third invoice.
        $orderLine = new OrderLineItemFactory()->for(new OrderFactory()->for(new CustomerFactory()))->for($this->product)->createOne(['contract_period' => 3, 'billing_period' => 1]);
        $price = new Price(
            type: ProductPriceType::REGISTRATION,
            billingPeriod: 1,
            productId: $this->product->id,
            productGroupUuid: $this->product->productGroup->uuid,
            regularPrice: 100,
            contractPeriod: 3,
            orderable: true,
            is_default: true,
            calculatedPrice: 72
        );

        $price->appliedPriceComponents = [
            new RegistrationPriceComponent(price: 100),
            new IntroductionPriceComponent(fixedDiscount: null, percentageDiscount: 10, fixedPrice: null, newPrice: 90, remainingUses: 1, maxUsesPerCustomer: 1, firstMonthsDiscountPeriod: 2, appliedOrder: 2),
            new VoucherPriceComponent(fixedDiscount: null, percentageDiscount: 20, newPrice: 72, appliedAmount: 18, voucher: new VoucherFactory()->createOne(), appliedOrder: 3),
        ];

        $this->service->persistOrderLineItemPrice($orderLine, $price);

        self::assertSame(100, $orderLine->gross_price);
        self::assertSame(72, $orderLine->net_price);

        $prices = $orderLine->prices->sortBy('valid_from')->values();

        $price1 = $prices->shift();
        $price2 = $prices->shift();
        $price3 = $prices->shift();

        self::assertInstanceOf(OrderLinePrice::class, $price1);
        self::assertInstanceOf(OrderLinePrice::class, $price2);
        self::assertInstanceOf(OrderLinePrice::class, $price3);

        self::assertDatabaseHas(
            'order_line_price_components',
            [
                'order_line_price_id' => $price1->id,
                'type' => PriceComponentType::REGISTRATION,
                'fixed_price' => 100,
                'new_price' => 100,
                'order_applied' => 1,
            ]
        );
        self::assertDatabaseHas(
            'order_line_price_components',
            [
                'order_line_price_id' => $price1->id,
                'type' => PriceComponentType::INTRODUCTION,
                'percentage_discount' => 10,
                'new_price' => 90,
                'order_applied' => 2,
            ]
        );
        self::assertDatabaseHas(
            'order_line_price_components',
            [
                'order_line_price_id' => $price1->id,
                'type' => PriceComponentType::VOUCHER,
                'percentage_discount' => 20,
                'new_price' => 72,
                'order_applied' => 3,
            ]
        );
        self::assertDatabaseHas(
            'order_line_price_components',
            [
                'order_line_price_id' => $price2->id,
                'type' => PriceComponentType::REGISTRATION,
                'fixed_price' => 100,
                'new_price' => 100,
                'order_applied' => 1,
            ]
        );
        self::assertDatabaseHas(
            'order_line_price_components',
            [
                'order_line_price_id' => $price2->id,
                'type' => PriceComponentType::INTRODUCTION,
                'percentage_discount' => 10,
                'new_price' => 90,
                'order_applied' => 2,
            ]
        );
        self::assertDatabaseHas(
            'order_line_price_components',
            [
                'order_line_price_id' => $price2->id,
                'type' => PriceComponentType::VOUCHER,
                'percentage_discount' => 20,
                'new_price' => 72,
                'order_applied' => 3,
            ]
        );
        self::assertDatabaseHas(
            'order_line_price_components',
            [
                'order_line_price_id' => $price3->id,
                'type' => PriceComponentType::REGISTRATION,
                'fixed_price' => 100,
                'new_price' => 100,
                'order_applied' => 1,
            ]
        );
        self::assertDatabaseMissing(
            'order_line_price_components',
            [
                'order_line_price_id' => $price3->id,
                'type' => PriceComponentType::INTRODUCTION,
            ]
        );
        self::assertDatabaseHas(
            'order_line_price_components',
            [
                'order_line_price_id' => $price3->id,
                'type' => PriceComponentType::VOUCHER,
                'percentage_discount' => 20,
                'new_price' => 80,
                'order_applied' => 2,
            ]
        );
    }

    #[Test]
    public function persistingCustomSubscriptionPriceDeletesAlreadySavedFuturePrices(): void
    {
        // Overriding a subscription price is essentially a breach of contract, because that price is used for each
        // invoice within the contract period. That price is different than what the customer agreed to when the
        // subscription is ordered.
        //
        // We might have already calculated all "billing cycles" (i.e. a subscription price for each invoice), so
        // those will be removed, and we mark the custom price override as such.

        $subscription = new SubscriptionFactory()->for(new CustomerFactory()->createOne())->for($this->product)->createOne();

        $subscriptionPrice = new SubscriptionPrice();
        $subscriptionPrice->subscription_id = $subscription->id;
        $subscriptionPrice->net_price = 123;
        $subscriptionPrice->valid_from = $subscription->start_date;
        $subscriptionPrice->save();

        $subscriptionPriceComponent = new SubscriptionPriceComponent();
        $subscriptionPriceComponent->subscription_price_id = $subscriptionPrice->id;
        $subscriptionPriceComponent->type = PriceComponentType::REGISTRATION;
        $subscriptionPriceComponent->fixed_price = 123;
        $subscriptionPriceComponent->new_price = 123;
        $subscriptionPriceComponent->order_applied = 1;
        $subscriptionPriceComponent->save();

        $subscription->subscription_price_id = $subscriptionPrice->id;
        $subscription->save();

        $futureSubscriptionPrice = new SubscriptionPrice();
        $futureSubscriptionPrice->subscription_id = $subscription->id;
        $futureSubscriptionPrice->net_price = 123;
        $futureSubscriptionPrice->valid_from = $subscription->start_date->addMonths($subscription->billing_period);
        $futureSubscriptionPrice->save();

        $futureSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $futureSubscriptionPriceComponent->subscription_price_id = $futureSubscriptionPrice->id;
        $futureSubscriptionPriceComponent->type = PriceComponentType::REGISTRATION;
        $futureSubscriptionPriceComponent->fixed_price = 123;
        $futureSubscriptionPriceComponent->new_price = 123;
        $futureSubscriptionPriceComponent->order_applied = 1;
        $futureSubscriptionPriceComponent->save();

        $this->service->persistCustomPrice($subscription, 456, true, CustomPriceReasonType::MANUAL_NOVA_OVERRIDE);

        self::assertSame(456, $subscription->net_price);

        // Make sure that the current subscription price is a custom one.
        self::assertDatabaseHas(
            'subscription_prices',
            [
                'subscription_id' => $subscription->id,
                'net_price' => 456,
                'valid_from' => $subscription->start_date,
            ]
        );
        // dd($subscription->subscription_price_id);
        self::assertDatabaseHas(
            'subscription_price_components',
            [
                'subscription_price_id' => $subscription->activePrice?->id,
                'type' => PriceComponentType::CUSTOM_ONE_OFF,
                'fixed_price' => 456,
                'new_price' => 456,
            ]
        );

        self::assertModelMissing($futureSubscriptionPrice);
        self::assertModelMissing($futureSubscriptionPriceComponent);
    }

    #[Test]
    public function persistIndefiniteCustomSubscriptionPrice(): void
    {
        $subscription = new SubscriptionFactory()->for(new CustomerFactory()->createOne())->for($this->product)->createOne();

        $this->service->persistCustomPrice($subscription, 654, false, CustomPriceReasonType::FIXED_MIGRATION_PRICE);

        self::assertSame(654, $subscription->net_price);

        self::assertDatabaseHas(
            'subscription_prices',
            [
                'subscription_id' => $subscription->id,
                'net_price' => 654,
                'valid_from' => $subscription->start_date,
            ]
        );
        self::assertDatabaseHas(
            'subscription_price_components',
            [
                'subscription_price_id' => $subscription->activePrice?->id,
                'type' => PriceComponentType::CUSTOM_INDEFINITE,
                'fixed_price' => 654,
                'new_price' => 654,
            ]
        );
    }
}
