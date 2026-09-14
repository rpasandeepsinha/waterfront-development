<?php

declare(strict_types=1);

namespace Tests\Domain\ResellerHosting\Services;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ResellerHostingDeploymentFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Hosting\DirectAdmin\Mailer\MailDirectAdminDetails;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\ResellerHosting\Exceptions\ResellerHostingException;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\ResellerHosting\Parameters\ResellerHostingParameters;
use Waterfront\Domain\ResellerHosting\Services\DirectAdminResellerHostingService;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(DirectAdminResellerHostingService::class)]
class DirectAdminResellerHostingServiceTest extends IntegrationTestCase
{
    private Customer $customer;

    private Server $daServer;

    private Product $resellerProduct;

    private DirectAdminResellerHostingService $resellerHostingService;

    private Subscription $subscription;

    private Subscription $domainSubscription;

    private Provider $hostingProvider;

    protected function setUp(): void
    {
        parent::setUp();

        new ServerFactory()->createOne([
            'type' => ServerType::DIRECTADMIN,
            'name' => 'Test DirectAdmin Server',
            'hostname' => 'testserver.testdomain.nl',
            'ipv4' => '127.0.0.1',
            'ipv6' => '2001:1460:2:0:1c21:1fff:fe00:1aa',
            'owner' => 'Realhosting',
            'allow_new_websites' => true,
            'secret_key' => '',
        ]);

        // Create the reseller hosting product group
        $resellerProductGroup = new ProductGroupFactory()->createOne([
            'name' => 'Reseller hosting',
            'slug' => 'reseller-hosting',
        ]);

        // Create a reseller Product with a registration price
        $this->resellerProduct = new ProductFactory()->createOne([
            'product_group_id' => $resellerProductGroup->id,
            'name' => 'Reseller Brons',
            'slug' => 'hosting_reseller_brons',
            'description' => 'Reseller hosting start',
            'orderable' => 1,
            'weight' => 1,
        ]);

        $this->customer = new CustomerFactory()->createOne();
        $this->hostingProvider = new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::DIRECTADMIN,
            'enabled' => true,
            'default' => true,
        ]);
        $this->daServer = new ServerFactory()->directadmin()->createOne();

        $this->subscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'domain' => 'testdomain.nl',
                'product_uuid' => $this->resellerProduct->uuid,
                'customer_id' => $this->customer->getKey(),
                'gross_price' => 1120,
                'net_price' => 1120,
                'contract_period' => 12,
            ]);

        new ResellerHostingDeploymentFactory()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'server_id' => $this->daServer->id,
            'directadmin_customer_username' => 'DaReseller',
            'provider_id' => $this->hostingProvider->id,
        ]);

        // Create procuct group Domein
        $domainProductGroup = new ProductGroupFactory()->createOne([
            'name' => 'Domein',
            'slug' => 'extension',
        ]);

        //Create a domein Product
        $domainProduct = new ProductFactory()->createOne([
            'product_group_id' => $domainProductGroup->id,
            'name' => '.nl',
            'slug' => 'extension_nl',
            'description' => '.nl Domein',
            'orderable' => 1,
            'weight' => 1,
        ]);

        $productPriceDomain = new ProductPriceComponentFactory()
            ->for($domainProduct)
            ->registration()
            ->createOne(['price' => 600]);

        $this->domainSubscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'domain' => 'testdomain.nl',
            'product_uuid' => $domainProduct->uuid,
            'gross_price' => $productPriceDomain->price,
            'net_price' => $productPriceDomain->price,
            'contract_period' => $productPriceDomain->contract_period,
            'billing_period' => $productPriceDomain->billing_period,
        ]);

        $this->resellerHostingService = self::resolve(DirectAdminResellerHostingService::class);
    }

    #[Test]
    public function createResellerHosting(): void
    {
        self::assertEmailsSend([
            MailDirectAdminDetails::class,
        ]);

        $resellerHostingService = self::resolve(DirectAdminResellerHostingService::class);

        $result = $resellerHostingService->create(
            contactPersonName: $this->customer->name,
            contactEmail: $this->customer->email,
            customerEmail: $this->customer->email,
            customerUuid: $this->customer->uuid,
            subscriptionUuid: $this->subscription->uuid,
            specs: [
                ['product_id' => $this->resellerProduct->id],
            ],
            providerId: 1,
            server: $this->daServer,
        );

        self::assertSame(TechnicalStatus::OK->value, $result);

        self::assertDatabaseHas('reseller_hosting_deployments', [
            'subscription_uuid' => $this->subscription->uuid,
            'server_id' => $this->daServer->id,
        ]);
    }

    #[Test]
    public function listResellers(): void
    {
        $resellers = $this->resellerHostingService->list($this->daServer);

        self::assertContains('TestResellerUser', $resellers);
    }

    #[Test]
    public function getResellerUsers(): void
    {
        $resellerSubscription = new ResellerHostingDeploymentFactory()->createOne([
            'provider_id' => $this->hostingProvider->id,
        ]);

        $resellerUserList = $this->resellerHostingService->getSubAccounts($resellerSubscription);

        self::assertContains('customer1', $resellerUserList, 'customer1 not created');
        self::assertContains('customer2', $resellerUserList, 'customer2 not created');
    }

    #[Test]
    public function setNameServerForDomain(): void
    {
        $domainDeployment = new DomainDeploymentFactory()->createOne([
            'subscription_uuid' => $this->domainSubscription->uuid,
            'last_result' => json_encode([]),
            'last_result_received' => CarbonImmutable::now(),
            'provider_id' => ProviderFactory::new()->createOne([
                'slug' => ProviderSlug::OPEN_PROVIDER,
                'enabled' => true,
                'default' => true,
                'type' => ProviderType::DOMAIN,
            ])->id,
        ]);

        $dnsProductGroup = ProductGroupFactory::new()->dns()->createOne();

        $dnsSubscription = SubscriptionFactory::new()
            ->withCustomer()
            ->for(ProductFactory::new()->for($dnsProductGroup)->createOne())
            ->forDomain('testdomain.nl')
            ->parentSubscription($this->domainSubscription)
            ->createOne();

        DnsDeploymentFactory::new()->for($dnsSubscription)->create();

        self::assertTrue($this->resellerHostingService->setNameserversForDomain($domainDeployment, $this->daServer));
    }

    #[Test]
    public function terminate(): void
    {
        self::assertInstanceOf(ResellerHostingDeployment::class, $this->subscription->resellerHostingDeployment);

        $result = $this->resellerHostingService->terminate($this->subscription->resellerHostingDeployment);

        self::assertTrue($result);
    }

    #[Test]
    public function resetPassword(): void
    {
        self::assertEmailsSend([
            MailDirectAdminDetails::class,
        ]);

        $resellerHostingService = self::resolve(DirectAdminResellerHostingService::class);

        $parameters = $this->generateResellerParameters();

        $result = $resellerHostingService->resetPassword($parameters, $this->customer->uuid);

        self::assertArrayHasKey('username', $result);
        self::assertArrayHasKey('password', $result);
        self::assertSame($result['username'], $parameters->username);
        self::assertSame($result['password'], $parameters->password);
    }

    #[Test]
    public function restPasswordMissingOrWrongServer(): void
    {
        $server = new ServerFactory()->createOne();

        $missingServerSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'domain' => 'testdomain.nl',
                'product_uuid' => $this->resellerProduct->uuid,
                'customer_id' => $this->customer->id,
                'gross_price' => 1120,
                'net_price' => 1120,
                'contract_period' => 12,
            ]);

        $invalidUsername = 'DaResellerInvalid';

        new ResellerHostingDeploymentFactory()->createOne([
            'subscription_uuid' => $missingServerSubscription->uuid,
            'server_id' => $server->id,
            'directadmin_customer_username' => $invalidUsername,
            'provider_id' => $this->hostingProvider->id,
        ]);

        $parameters = $this->generateResellerParameters($invalidUsername);

        $this->expectException(ResellerHostingException::class);
        $this->expectExceptionMessageIs(
            'No compatible server was found to deploy reseller hosting packages in directadmin!',
        );

        $this->resellerHostingService->resetPassword($parameters, $this->customer->uuid);
    }

    #[Test]
    public function restPasswordMissingOrWrongUsername(): void
    {
        $parameters = $this->generateResellerParameters('DaResellerInvalid');

        $this->expectException(ResellerHostingException::class);
        $this->expectExceptionCode(1);
        $this->resellerHostingService->resetPassword($parameters, $this->customer->uuid);
    }

    private function generateResellerParameters(?string $username = null): ResellerHostingParameters
    {
        return new ResellerHostingParameters(
            contactPerson: $this->customer->getContactNameAttribute(),
            username: $username ?? $this->subscription->resellerHostingDeployment->directadmin_customer_username
                ?? 'test',
            password: 'supersecret',
            email: $this->customer->email,
            domain: $this->subscription->domain,
            ipv4Address: $this->subscription->resellerHostingDeployment?->server?->ipv4,
            ipv6Address: null,
            packageName: 'Mini',
            resellerHostingId: null,
            providerId: 1,
        );
    }
}
