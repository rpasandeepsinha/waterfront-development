<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Plesk;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(HostingService::class)]
class HostingServiceCustomerSharedTest extends IntegrationTestCase
{
    private Customer $customer;

    private Server $server;

    private string $domain;

    private Subscription $subscription;

    private HostingService $hostingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hostingService = self::resolve(HostingService::class);

        $this->customer = new CustomerFactory()->createOne();

        $this->server = new ServerFactory()->createOne();

        $this->domain = 'testdomain.nl';

        $productGroup = new ProductGroupFactory()->createOne([
            'name' => 'Hosting',
            'slug' => 'hosting',
        ]);

        $product = new ProductFactory()->createOne([
            'product_group_id' => $productGroup->id,
            'name' => 'Hosting basic',
            'slug' => 'hosting_basic',
        ]);

        $this->subscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'domain' => $this->domain,
                'product_uuid' => $product->uuid,
                'customer_id' => $this->customer->id,
            ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
        ]);
    }

    #[Test]
    public function createHostingSuccess(): void
    {
        $this->app->bind(DnsService::class, fn (): DnsService => self::createStub(DnsService::class));

        new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::PLESK,
            'enabled' => true,
            'default' => true,
        ]);

        $this->hostingService->create(
            subscriptionUuid: $this->subscription->uuid,
            contactPersonName: $this->customer->getContactNameAttribute(),
            contactEmail: $this->customer->email,
            product: $this->subscription->product,
            customer: $this->customer,
            serverId: $this->server->id,
            domain: $this->domain,
        );

        self::assertDatabaseHas('subscriptions', [
            'uuid' => $this->subscription->uuid,
            'domain' => $this->domain,
            'technical_status' => TechnicalStatus::OK->value,
        ]);

        self::assertDatabaseHas('hosting_deployments', [
            'subscription_uuid' => $this->subscription->uuid,
        ]);
    }
}
