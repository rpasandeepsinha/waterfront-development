<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Services;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;

#[CoversNothing]
class ServiceControllerTest extends IntegrationTestCase
{
    private Customer $customer;

    public function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function manualProvisioningSubscriptionNullAllowed(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->manualSubscription())->createOne();
        new ProductPriceComponentFactory()->prolongation()->for($product)->createOne();

        $manualProvisioningSubscription = new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->createOne([
                'domain' => null,
            ]);

        $response = $this
            ->actingAsCustomer($this->customer)
            ->getJson($this->generateRoute('partners.subscriptions.index'))
            ->assertOk();

        $response->assertJsonPath('data.0.id', $manualProvisioningSubscription->id);
    }

    #[Test]
    public function addonSubscriptionNullAllowed(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->addon())->createOne();
        new ProductPriceComponentFactory()->prolongation()->for($product)->createOne();

        $addonSubscription = new SubscriptionFactory()
            ->for($product)
            ->createOne([
                'customer_id' => $this->customer->id,
                'domain' => null,
            ]);

        $response = $this
            ->actingAsCustomer($this->customer)
            ->getJson($this->generateRoute('partners.subscriptions.index'))
            ->assertOk();

        $response->assertJsonPath('data.0.id', $addonSubscription->id);
    }

    #[Test]
    public function serviceNotShowingNullDomains(): void
    {
        new SubscriptionFactory()
            ->has((new HostingDeploymentFactory()))
            ->for(new ProductFactory()->for(new ProductGroupFactory()->extension()))
            ->createOne([
                'customer_id' => $this->customer->id,
                'domain' => null,
            ]);

        $response = $this
            ->actingAsCustomer($this->customer)
            ->getJson($this->generateRoute('partners.subscriptions.index'))
            ->assertOk();

        $response->assertJsonMissingPath('data.0.id');
    }

    #[Test]
    public function customerServicesFromCorrectUser(): void
    {
        $differentCustomerSubscription = new SubscriptionFactory()
            ->has(
                new SslDeploymentFactory()
                    ->for(ProviderFactory::new()->createOne(['type' => ProviderType::SSL, 'slug' => ProviderSlug::OPEN_PROVIDER, 'enabled' => true, 'default' => true]), 'provider'),
                'sslDeployment'
            )
            ->for(new ProductFactory()->for(new ProductGroupFactory()->ssl()))
            ->for((new CustomerFactory()))
            ->createOne();

        $hostingProduct = new ProductFactory()->for(new ProductGroupFactory()->hosting())->createOne();
        new ProductPriceComponentFactory()->prolongation()->for($hostingProduct)->createOne();
        new SubscriptionFactory()
            ->has((new HostingDeploymentFactory()))
            ->for($hostingProduct)
            ->for($this->customer)
            ->createOne();

        $extensionProduct = new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne();
        new ProductPriceComponentFactory()->prolongation()->for($extensionProduct)->createOne();
        new SubscriptionFactory()
            ->has(new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne()))
            ->for($extensionProduct)
            ->for($this->customer)
            ->createOne();

        $response = $this
            ->actingAsCustomer($this->customer)
            ->getJson($this->generateRoute('partners.subscriptions.index'))
            ->assertOk();

        $response->assertJsonCount(2, 'data');
        $response->assertJsonMissing(['uuid' => $differentCustomerSubscription->uuid]);
    }

    #[Test]
    public function customerServices(): void
    {
        $hostingProduct = new ProductFactory()->for(new ProductGroupFactory()->hosting())->createOne();
        $domainProduct = new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne();

        $hostingRegistrationPrice = new ProductPriceComponentFactory()->for($hostingProduct)->registration()->createOne();
        $domainRegistrationPrice = new ProductPriceComponentFactory()->for($domainProduct)->registration()->createOne();

        new ProductPriceComponentFactory()->for($hostingProduct)->prolongation()->createOne();
        new ProductPriceComponentFactory()->for($domainProduct)->prolongation()->createOne();

        $hostingDeployment = new SubscriptionFactory()
            ->has(new HostingDeploymentFactory())
            ->for($hostingProduct)
            ->for($this->customer)
            ->createOne([
                'net_price' => $hostingRegistrationPrice->price,
            ]);

        $domainSubscription = new SubscriptionFactory()
            ->has(new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne()))
            ->for($domainProduct)
            ->for($this->customer)
            ->createOne([
                'net_price' => $domainRegistrationPrice->price,
            ]);

        $response = $this->actingAsCustomer($this->customer)
            ->getJson($this->generateRoute('partners.subscriptions.index'))
            ->assertOk();

        $response->assertJsonFragment(['id' => $hostingDeployment->id]);
        $response->assertJsonFragment(['id' => $domainSubscription->id]);
    }
}
