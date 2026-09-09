<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Subscriptions;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductAllowedChangeFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversNothing]
class PotentialUpgradesTest extends IntegrationTestCase
{
    private Customer $customer;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne([
            'credit_limit' => 1,
        ]);

        new ProviderFactory()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::PLESK, 'enabled' => true, 'default' => true]);
        new ServerFactory()->createOne();
        $hostingProductGroup = new ProductGroupFactory()->createOne([
            'name' => ProductGroupType::HOSTING,
            'slug' => ProductGroupType::HOSTING,
        ]);

        $basicHostingProduct = new ProductFactory()->createOne([
            'product_group_id' => $hostingProductGroup->id,
            'name' => 'basic',
        ]);

        $superHostingProduct = new ProductFactory()->createOne([
            'product_group_id' => $hostingProductGroup->id,
            'name' => 'super',
        ]);

        new ProductPriceComponentFactory()->prolongation()->createOne([
            'product_id' => $superHostingProduct->id,
            'price' => 110,
            'billing_period' => 12,
            'contract_period' => 12,
        ]);

        $this->subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'technical_status' => TechnicalStatus::OK->value,
            'product_uuid' => $basicHostingProduct->uuid,
            'contract_period' => 12,
            'net_price' => 100,
        ]);

        self::assertInstanceOf(Subscription::class, $this->subscription->first());

        ProductAllowedChangeFactory::new()->upgradeChange()->create([
            'from_product_id' => $basicHostingProduct->id,
            'to_product_id' =>  $superHostingProduct->id,
        ]);
    }

    #[Test]
    public function getPotentialUpgrades(): void
    {
        $response = $this->actingAsCustomer($this->customer)->json(
            'get',
            $this->generateRoute('partners.subscriptions.potential.upgrades', $this->subscription->uuid),
        );
        $response->assertOk();

        $upgradeName = 'super';

        $json = $response->json();
        assert(is_array($json));

        $upgrades = Arr::get($json, 'upgrades');
        assert(is_array($upgrades));

        $entry = new Collection($upgrades)->filter(fn ($upgrade): bool => Arr::get($upgrade, 'product.name') === $upgradeName)->first();

        self::assertSame(
            $upgradeName,
            Arr::get($entry, 'product.name'),
            'potential upgrades heeft geen product genaamd super'
        );
    }
}
