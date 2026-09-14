<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\DataProvider\DomainSubscriptionDataProvider;
use Tests\Factories\ExperimentFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\SubscriptionMutationFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\SubscriptionPrice;
use Waterfront\Domain\Pricing\Models\SubscriptionPriceComponent;
use Waterfront\Domain\Subscriptions\Services\SubscriptionRenewService;

#[CoversClass(SubscriptionRenewService::class)]
class SubscriptionRenewServiceTest extends IntegrationTestCase
{
    private SubscriptionRenewService $renewService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renewService = self::resolve(SubscriptionRenewService::class);
    }

    #[Test]
    public function renewWithoutMutationDoesNotChangeProductAndPeriods(): void
    {
        $subscription = DomainSubscriptionDataProvider::subscription();
        $renewPrice = 100100;
        $billingPeriod = $subscription->billing_period;
        $contractPeriod = $subscription->contract_period;
        $endDate = clone $subscription->end_date;
        $productId = $subscription->product->id;

        new ProductPriceComponentFactory()
            ->for($subscription->product)
            ->prolongation()
            ->createOne([
                'billing_period' => $billingPeriod,
                'contract_period' => $contractPeriod,
                'price' => $renewPrice,
            ]);

        $this->renewService->renew($subscription);

        $subscription->refresh();

        self::assertSame($billingPeriod, $subscription->billing_period);
        self::assertSame($contractPeriod, $subscription->contract_period);
        self::assertSame($productId, $subscription->product->id);
        self::assertSame(
            $endDate->addMonths($contractPeriod)->format('Y-m-d'),
            $subscription->end_date->format('Y-m-d'),
        );
    }

    #[Test]
    public function renewWithMutationChangesPeriods(): void
    {
        $subscription = DomainSubscriptionDataProvider::subscription();
        $renewPrice = 100100;
        $productId = $subscription->product->id;
        $newBillingPeriod = $subscription->billing_period * 2;
        $newContractPeriod = $subscription->contract_period * 2;
        $endDate = clone $subscription->end_date;

        new ProductPriceComponentFactory()
            ->for($subscription->product)
            ->prolongation()
            ->createOne([
                'billing_period' => $newBillingPeriod,
                'contract_period' => $newContractPeriod,
                'price' => $renewPrice,
            ]);

        new SubscriptionMutationFactory()
            ->for($subscription)
            ->for($subscription->product)
            ->createOne([
                'billing_period' => $newBillingPeriod,
                'contract_period' => $newContractPeriod,
                'gross_price' => $renewPrice,
                'net_price' => $renewPrice,
            ]);

        $this->renewService->renew($subscription);

        $subscription->refresh();

        self::assertSame($newBillingPeriod, $subscription->billing_period);
        self::assertSame($newContractPeriod, $subscription->contract_period);
        self::assertSame($productId, $subscription->product->id);
        self::assertSame(
            $endDate->addMonths($newContractPeriod)->format('Y-m-d'),
            $subscription->end_date->format('Y-m-d'),
        );
    }

    #[Test]
    public function renewWithMutationChangesPeriodsAndUsesMutationPrice(): void
    {
        $subscription = DomainSubscriptionDataProvider::subscription();
        $renewPrice = 100100;
        $productId = $subscription->product->id;
        $newBillingPeriod = $subscription->billing_period * 2;
        $newContractPeriod = $subscription->contract_period * 2;
        $endDate = clone $subscription->end_date;

        $productPrice = new ProductPriceComponentFactory()
            ->for($subscription->product)
            ->prolongation()
            ->createOne([
                'billing_period' => $newBillingPeriod,
                'contract_period' => $newContractPeriod,
                'price' => $renewPrice,
            ]);

        new SubscriptionMutationFactory()
            ->for($subscription)
            ->for($subscription->product)
            ->createOne([
                'billing_period' => $newBillingPeriod,
                'contract_period' => $newContractPeriod,
                'gross_price' => $renewPrice,
                'net_price' => $renewPrice,
            ]);

        // Change the product price after the mutation has been created
        $productPrice->price = $renewPrice * 2;
        $productPrice->save();

        $this->renewService->renew($subscription);

        $subscription->refresh();

        self::assertSame($newBillingPeriod, $subscription->billing_period);
        self::assertSame($newContractPeriod, $subscription->contract_period);
        self::assertSame($productId, $subscription->product->id);
        self::assertSame(
            $endDate->addMonths($newContractPeriod)->format('Y-m-d'),
            $subscription->end_date->format('Y-m-d'),
        );
    }

    #[Test]
    public function renewParentWithMutationChildWithoutShouldSyncPeriods(): void
    {
        $subscription = DomainSubscriptionDataProvider::subscription();
        $childSubscription = new SubscriptionFactory()
            ->for($subscription->customer)
            ->for($subscription->product)
            ->createOne([
                'parent_subscription_id' => $subscription->id,
                'end_date' => $subscription->end_date->subWeek(),
            ]);

        $subscription->refresh();
        $childSubscription->refresh();

        $renewPrice = 100100;
        $newBillingPeriod = $subscription->billing_period * 2;
        $newContractPeriod = $subscription->contract_period * 2;
        $endDate = clone $subscription->end_date;

        new ProductPriceComponentFactory()
            ->for($subscription->product)
            ->prolongation()
            ->createOne([
                'billing_period' => $newBillingPeriod,
                'contract_period' => $newContractPeriod,
                'price' => $renewPrice,
            ]);

        new SubscriptionMutationFactory()
            ->for($subscription)
            ->for($subscription->product)
            ->createOne([
                'billing_period' => $newBillingPeriod,
                'contract_period' => $newContractPeriod,
                'gross_price' => $renewPrice,
                'net_price' => $renewPrice,
            ]);

        $this->renewService->renew($subscription);

        $subscription->refresh();
        $childSubscription->refresh();

        self::assertSame($newBillingPeriod, $subscription->billing_period);
        self::assertSame($newContractPeriod, $subscription->contract_period);
        self::assertSame($newBillingPeriod, $childSubscription->billing_period);
        self::assertSame($newContractPeriod, $childSubscription->contract_period);
        self::assertSame(
            $endDate->addMonths($newContractPeriod)->format('Y-m-d'),
            $subscription->end_date->format('Y-m-d'),
        );
        self::assertSame(
            $endDate->addMonths($newContractPeriod)->format('Y-m-d'),
            $childSubscription->end_date->format('Y-m-d'),
        );
    }

    /**
     * The applied component has to end up on the subscription, because that saved component is what stops the variant
     * from being handed out again on the next renewal.
     */
    #[Test]
    public function renewOnTheFirstIterationAfterRegistrationAppliesAndPersistsTheExperimentPrice(): void
    {
        $subscription = DomainSubscriptionDataProvider::subscription();

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

        new ProductPriceComponentFactory()
            ->for($subscription->product)
            ->registration()
            ->createOne([
                'billing_period' => $subscription->billing_period,
                'contract_period' => $subscription->contract_period,
                'price' => 100100,
            ]);
        new ProductPriceComponentFactory()
            ->for($subscription->product)
            ->prolongation()
            ->createOne([
                'billing_period' => $subscription->billing_period,
                'contract_period' => $subscription->contract_period,
                'price' => 200200,
            ]);

        new ProductPriceComponentFactory()->for($subscription->product)->createOne([
            'type' => PriceComponentType::EXPERIMENT_PRICE_LADDER,
            'billing_period' => $subscription->billing_period,
            'contract_period' => $subscription->contract_period,
            'price' => 150,
        ]);

        $experiment = new ExperimentFactory()->createOne();
        $experiment->subscriptions()->attach($subscription);
        $experiment->products()->attach($subscription->product);

        $this->renewService->renew($subscription);

        $subscription->refresh();

        self::assertSame(150, $subscription->net_price);
        self::assertTrue(
            $subscription
                ->activePrice
                ?->components()
                ->where('type', PriceComponentType::EXPERIMENT_PRICE_LADDER)
                ->exists(),
        );
    }
}
