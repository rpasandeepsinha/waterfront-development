<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Services;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;

#[CoversNothing]
class GetAllServicesTest extends IntegrationTestCase
{
    public const string DOMAIN = 'sandwave.io';

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function index(): void
    {
        $group = new ProductGroupFactory()->extension()->createOne();

        $product = new ProductFactory()->for($group)->createOne([
            'name' => '.com',
            'slug' => 'com_domain',
        ]);
        $subscription = new SubscriptionFactory()->for($product)->for($this->customer)->createOne([
            'domain' => '',
        ]);

        $groupHosting = new ProductGroupFactory()->hosting()->createOne();

        $productHosting = new ProductFactory()->for($groupHosting)->createOne([
            'name' => 'Basic Hosting',
            'slug' => 'hosting_basic',
        ]);

        $productPriceHosting = new ProductPriceComponentFactory()->for($productHosting)->registration()->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 120,
        ]);
        new ProductPriceComponentFactory()->for($productHosting)->createOne(['type' => PriceComponentType::PROMOTION, 'price' => 96]);

        new ProductPriceComponentFactory()->for($productHosting)->prolongation()->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 120,
        ]);

        new SubscriptionFactory()->for($productHosting)->for($this->customer)->createOne([
            'domain' => self::DOMAIN,
            'gross_price' => $productPriceHosting->price,
            'net_price' => $productPriceHosting->price,
            'technical_status' => DomainStatus::ACTIVE->value,
            'contract_period' => 12,
            'billing_period' => 12,
        ]);

        new DomainDeploymentFactory()
            ->for(new ProviderFactory()
                ->domainOpenProvider()
                ->createOne())
            ->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);

        $response = $this
            ->actingAsCustomer($this->customer)
            ->getJson($this->generateRoute('partners.subscriptions.index'))
            ->assertOk()
            ->assertJson(['data' => [['domain' => self::DOMAIN]]])
            ->assertJsonMissing(['data' => [['id' => $subscription->id]]]);

        self::assertFalse((bool) $response->json('data.0.in_transfer'));
    }
}
