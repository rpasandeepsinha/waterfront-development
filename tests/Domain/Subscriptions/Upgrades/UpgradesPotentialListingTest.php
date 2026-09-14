<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Upgrades;

use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductAllowedChangeFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionChangeService;

#[CoversClass(SubscriptionChangeService::class)]
class UpgradesPotentialListingTest extends IntegrationTestCase
{
    /**
     * @var array<int>
     */
    public const array PERIODS = [
        12,
        24,
        36,
    ];

    private SubscriptionChangeService $upgradeService;

    /**
     * @var Collection<int, Subscription>
     */
    private Collection $allowedToUpgrade;

    /**
     * @var Collection<int, Subscription>
     */
    private Collection $notAllowedToUpgrade;

    public function setUp(): void
    {
        parent::setUp();

        $subscriptions = new Collection();

        $customer = new CustomerFactory()->createOne();

        $productGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::HOSTING,
        ]);

        $product_brons = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'hosting_brons',
        ]);

        $product_zilver = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'hosting_zilver',
        ]);

        $product_groot = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'hosting_groot',
        ]);

        ProductAllowedChangeFactory::new()->upgradeChange()->create([
            'from_product_id' => $product_brons->id,
            'to_product_id' => $product_zilver->id,
        ]);

        ProductAllowedChangeFactory::new()->upgradeChange()->create([
            'from_product_id' => $product_brons->id,
            'to_product_id' => $product_groot->id,
        ]);

        ProductAllowedChangeFactory::new()->upgradeChange()->create([
            'from_product_id' => $product_zilver->id,
            'to_product_id' => $product_groot->id,
        ]);

        foreach (self::PERIODS as $period) {
            $subscription_brons = $this->createPeriodSubscription($product_brons, $customer, $period);
            $subscription_silver = $this->createPeriodSubscription($product_zilver, $customer, $period);
            $subscription_groot = $this->createPeriodSubscription($product_groot, $customer, $period);

            $subscriptions->add($subscription_brons);
            $subscriptions->add($subscription_silver);
            $subscriptions->add($subscription_groot);
        }

        $this->upgradeService = self::resolve(SubscriptionChangeService::class);

        $this->allowedToUpgrade = $subscriptions->filter(
            fn ($subscription): bool => $subscription->product->slug !== 'hosting_groot',
        );

        $this->notAllowedToUpgrade = $subscriptions->filter(
            fn ($subscription): bool => $subscription->product->slug === 'hosting_groot',
        );

        $customer->credit_limit = 999999;
        $customer->save();
        $customer->fresh();
    }

    #[Test]
    public function gatherPotentialUpgrades(): void
    {
        $this->allowedToUpgrade->each(function ($sub): void {
            $potentials = $this->upgradeService->getPotentialChanges(ProductChangeType::UPGRADE, $sub);
            self::assertNotEmpty(
                $potentials->toArray(),
                "subscription for prod: {$sub->product->name} did not return potential upgrades.",
            );
        });
    }

    #[Test]
    public function noPotentialUpgrades(): void
    {
        $this->notAllowedToUpgrade->each(function ($sub): void {
            $potentials = $this->upgradeService->getPotentialChanges(ProductChangeType::UPGRADE, $sub);
            self::assertEmpty(
                $potentials->toArray(),
                "subscription for prod: {$sub->product->name} did return potential upgrades while it should have been empty!.",
            );
        });
    }

    private function createPeriodSubscription(Product $product, Customer $customer, int $period): Subscription
    {
        $product_price = new ProductPriceComponentFactory()
            ->for($product)
            ->registration()
            ->createOne([
                'contract_period' => $period,
                'billing_period' => $period,
            ]);

        new ProductPriceComponentFactory()
            ->for($product)
            ->prolongation()
            ->createOne([
                'contract_period' => $period,
                'billing_period' => $period,
            ]);

        return new SubscriptionFactory()
            ->for($product)
            ->for($customer)
            ->createOne([
                'contract_period' => $period,
                'net_price' => $product_price->price,
                'billing_period' => $period,
            ]);
    }
}
