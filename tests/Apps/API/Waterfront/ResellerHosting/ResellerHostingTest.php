<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\ResellerHosting;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ResellerHostingDeploymentFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Hosting\DirectAdmin\Mailer\MailDirectAdminDetails;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\DirectAdminJsonClient\DirectAdminClient;

#[CoversNothing]
class ResellerHostingTest extends IntegrationTestCase
{
    private Customer $customer;

    private ProductGroup $resellerProductGroup;

    private Provider $hostingProvider;

    public function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $this->customer = new CustomerFactory()->createOne();

        $this->resellerProductGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::RESELLER_HOSTING,
            'name' => 'Reseller Hosting',
        ]);

        $this->hostingProvider = ProviderFactory::new()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::DIRECTADMIN,
            'enabled' => true,
            'default' => true,
        ]);
    }

    #[Test]
    public function listPackages(): void
    {
        $resellerProductGroupDNS = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::DNS,
            'name' => 'DNS group',
        ]);

        //Create a valid subscription, only this may be selected
        $resellerProduct = new ProductFactory()->createOne([
            'product_group_id' => $this->resellerProductGroup->id,
            'name' => 'reseller-brons',
            'slug' => 'hosting_reseller_brons',
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $resellerProduct->uuid,
        ]);

        new ResellerHostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $this->hostingProvider->id,
        ]);

        $subscriptionCanceled = new SubscriptionFactory()
            ->for($this->customer)
            ->for($resellerProduct)
            ->createOne([
                'administrative_status' => AdministrativeStatus::CANCELED->value,
            ]);

        new ResellerHostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscriptionCanceled->uuid,
            'provider_id' => $this->hostingProvider->id,
        ]);

        $subscriptionDeleted = new SubscriptionFactory()
            ->for($this->customer)
            ->for($resellerProduct)
            ->createOne([
                'administrative_status' => AdministrativeStatus::ARCHIVED->value,
            ]);

        new ResellerHostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscriptionDeleted->uuid,
            'provider_id' => $this->hostingProvider->id,
        ]);

        //Create an invalid subscription, it should not be selected because of grouptype DNS
        $theBestProduct = new ProductFactory()->createOne([
            'product_group_id' => $resellerProductGroupDNS->id,
            'name' => 'DNS',
            'slug' => 'DNS-is-thebest',
        ]);

        new SubscriptionFactory()
            ->for($this->customer)
            ->for($theBestProduct)
            ->createOne();

        $response = $this->actingAsCustomer($this->customer)
            ->json('GET', $this->generateRoute('partners.reseller-hosting.index'))
            ->assertOk()
            ->assertJsonFragment([
                'product_name' => $resellerProduct->name,
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
            ]);

        // We expect only our Reseller product not the DNS product or products with the status canceled / deleted.
        $json = $response->json();
        self::assertIsArray($json);
        self::assertCount(1, $json);
    }

    #[Test]
    public function listPackagesNotFound(): void
    {
        $this->actingAsCustomer($this->customer)
            ->getJson($this->generateRoute('partners.reseller-hosting.index'))
            ->assertOk()
            ->assertJsonFragment(['data' => []]);
    }

    #[Test]
    public function getSSOUrl(): void
    {
        $expectedUrl = 'https://directadmin.sso.testing:1337/login-hash';

        $mockClient = self::createMock(DirectAdminClient::class);
        $mockClient->expects(self::once())->method('createLoginUrl')->willReturn($expectedUrl);

        $this->app->bind(DirectAdminClient::class, fn () => $mockClient);

        $resellerProduct = new ProductFactory()->createOne([
            'product_group_id' => $this->resellerProductGroup->id,
            'slug' => 'hosting_reseller_brons',
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $resellerProduct->uuid,
            'start_date' => CarbonImmutable::now(),
            'end_date' => CarbonImmutable::now(),
        ]);

        new ResellerHostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $this->hostingProvider->id,
        ]);

        $this->actingAsCustomer($this->customer)
            ->getJson($this->generateRoute('partners.reseller-hosting.sso', $subscription->uuid))
            ->assertOk()
            ->assertJsonFragment(['url' => $expectedUrl]);
    }

    #[Test]
    public function showPackage(): void
    {
        $subscription = $this->createSubscription();
        $response = $this->actingAsCustomer($this->customer)
            ->getJson($this->generateRoute('partners.reseller-hosting.show', $subscription->uuid))
            ->assertOk()
            ->assertJsonFragment([
                'uuid' => $subscription->uuid,
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
            ]);

        // We expect only our Reseller product.
        $json = $response->json();
        self::assertIsArray($json);
        self::assertCount(1, $json);
    }

    #[Test]
    public function showPackageWrongCustomer(): void
    {
        $resellerProduct = new ProductFactory()->createOne([
            'product_group_id' => $this->resellerProductGroup->id,
            'slug' => 'hosting_reseller_brons',
        ]);

        $customer2 = new CustomerFactory()->createOne();

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'customer_id' => $customer2->id,
                'product_uuid' => $resellerProduct->uuid,
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now(),
            ]);

        new ResellerHostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $this->hostingProvider->id,
        ]);

        $product = new ProductFactory()->createOne([
            'product_group_id' => $this->resellerProductGroup->id,
            'slug' => 'hosting_reseller_gold',
        ]);

        $subscriptionGold = new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now(),
            ]);

        new ResellerHostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscriptionGold->uuid,
            'provider_id' => $this->hostingProvider->id,
        ]);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.reseller-hosting.show', $subscription->uuid),
            )
            ->assertForbidden()
            ->assertJsonFragment([
                'message' => 'This action is unauthorized.',
            ]);
    }

    #[Test]
    public function showPackageInvalidUuid(): void
    {
        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.reseller-hosting.show', 'random2-3014-11ec-a1e8-d2478f8e83d5'),
            )
            ->assertNotFound();
    }

    #[Test]
    public function resetPassword(): void
    {
        self::assertEmailsSend([MailDirectAdminDetails::class]);

        $resellerProduct = new ProductFactory()->for($this->resellerProductGroup)->createOne([
            'slug' => 'hosting_reseller_brons',
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $resellerProduct->uuid,
            'start_date' => CarbonImmutable::now(),
            'end_date' => CarbonImmutable::now(),
        ]);

        new ResellerHostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'directadmin_customer_username' => 'DAUserName',
            'provider_id' => $this->hostingProvider->id,
        ]);

        $this->actingAsCustomer($this->customer)
            ->patchJson(
                $this->generateRoute('partners.reseller-hosting.reset-password', $subscription->uuid),
            )
            ->assertOk()
            ->assertJsonFragment([
                'username' => 'DAUserName',
            ]);
    }

    #[Test]
    public function resetPasswordSubscriptionNotFound(): void
    {
        $this->actingAsCustomer($this->customer)
            ->patchJson($this->generateRoute(
                'partners.reseller-hosting.reset-password',
                'random-3014-11ec-a1e8-d2478f8e83d5',
            ))
            ->assertNotFound();
    }

    #[Test]
    public function resetPasswordUsernameNotFound(): void
    {
        $resellerProduct = new ProductFactory()->createOne([
            'product_group_id' => $this->resellerProductGroup->id,
            'slug' => 'hosting_reseller_brons',
        ]);

        $subscriptionNoUSer = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $resellerProduct->uuid,
            'start_date' => CarbonImmutable::now(),
            'end_date' => CarbonImmutable::now(),
        ]);

        new ResellerHostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscriptionNoUSer->uuid,
            'directadmin_customer_username' => null,
            'provider_id' => $this->hostingProvider->id,
        ]);

        $this->actingAsCustomer($this->customer)
            ->patchJson($this->generateRoute(
                'partners.reseller-hosting.reset-password',
                $subscriptionNoUSer->uuid,
            ))
            ->assertUnprocessable();
    }

    #[Test]
    public function getResellerCustomers(): void //TODO: flaky ?
    {
        $resellerProduct = new ProductFactory()->createOne([
            'product_group_id' => $this->resellerProductGroup->id,
            'slug' => 'hosting_reseller_brons',
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $resellerProduct->uuid,
            'start_date' => CarbonImmutable::now(),
            'end_date' => CarbonImmutable::now(),
        ]);

        new ResellerHostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'directadmin_customer_username' => 'DAUserName',
            'provider_id' => $this->hostingProvider->id,
        ]);

        $this->actingAsCustomer($this->customer)
            ->getJson($this->generateRoute('partners.reseller-hosting.customers', $subscription->uuid))
            ->assertOk()
            ->assertJsonStructure(['customers']);
    }

    private function createSubscription(): Subscription
    {
        $productName = 'reseller-brons';

        $server = new ServerFactory()->createOne([
            'ipv4' => '127.0.0.1',
        ]);

        $resellerProduct = new ProductFactory()->createOne([
            'product_group_id' => $this->resellerProductGroup->id,
            'name' => $productName,
            'slug' => 'hosting_reseller_brons',
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $resellerProduct->uuid,
            'technical_status' => TechnicalStatus::OK->value,
            'start_date' => CarbonImmutable::now(),
            'end_date' => CarbonImmutable::now(),
        ]);

        new ResellerHostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'server_id' => $server->id,
            'provider_id' => $this->hostingProvider->id,
        ]);

        $product = new ProductFactory()->createOne([
            'product_group_id' => $this->resellerProductGroup->id,
            'name' => $productName,
            'slug' => 'reseller-hosting-gold',
        ]);

        $subscriptionGold = new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->createOne([
                'technical_status' => TechnicalStatus::OK->value,
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now(),
            ]);

        new ResellerHostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscriptionGold->uuid,
            'server_id' => $server->id,
            'provider_id' => $this->hostingProvider->id,
        ]);

        return $subscription;
    }
}
