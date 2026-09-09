<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\HostingDeploymentController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Servers\Models\Server;

#[CoversClass(HostingDeploymentController::class)]
class HostingDeploymentControllerTest extends IntegrationTestCase
{
    public const string DOMAIN = 'test.nl';

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function showHostingDeployment(): void
    {
        $hostingProduct = new ProductFactory()->hostingBrons()->createOne();
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($hostingProduct)
            ->createOne(['domain' => self::DOMAIN]);

        $deployment = new HostingDeploymentFactory()
            ->withDirectAdminProvider()
            ->createOne([
                'subscription_uuid' => $subscription->uuid,
            ]);

        $server = $deployment->server;
        self::assertInstanceOf(Server::class, $server);

        $response = $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.domain.hosting', ['domain' => self::DOMAIN]))
            ->assertOk();

        $content = $response->json();

        self::assertIsArray($content);
        self::assertSame($server->id, $content['server_id']);
        self::assertSame($server->ipv4, $content['server_ip']);
        self::assertSame($deployment->provider?->slug->value, $content['provider']);
        self::assertSame($deployment->directadmin_customer_username, $content['username']);
    }

    #[Test]
    public function updateProviderUpdatesTheHostingDeploymentProvider(): void
    {
        $hostingProduct = new ProductFactory()->hostingBrons()->createOne();
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($hostingProduct)
            ->createOne(['domain' => self::DOMAIN]);

        $deployment = new HostingDeploymentFactory()
            ->withDirectAdminProvider()
            ->createOne([
                'subscription_uuid' => $subscription->uuid,
            ]);

        $newProvider = new ProviderFactory()->pleskHosting()->createOne();

        $this->actingAsEmployee()
            ->putJson($this->generateRoute('admin.hosting.update-provider', [
                'hostingDeployment' => $subscription->uuid,
                'provider' => $newProvider->id,
            ]))
            ->assertOk()
            ->assertExactJson(['message' => 'Hosting provider updated successfully']);

        self::assertSame($newProvider->id, $deployment->refresh()->provider_id);
    }

    #[Test]
    public function updateProviderReturnsUnprocessableWhenProviderIsNotAHostingProvider(): void
    {
        $hostingProduct = new ProductFactory()->hostingBrons()->createOne();
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($hostingProduct)
            ->createOne(['domain' => self::DOMAIN]);

        $deployment = new HostingDeploymentFactory()
            ->withDirectAdminProvider()
            ->createOne([
                'subscription_uuid' => $subscription->uuid,
            ]);

        $nonHostingProvider = new ProviderFactory()->sslRtr()->createOne();

        $this->actingAsEmployee()
            ->putJson($this->generateRoute('admin.hosting.update-provider', [
                'hostingDeployment' => $subscription->uuid,
                'provider' => $nonHostingProvider->id,
            ]))
            ->assertUnprocessable()
            ->assertJson(['message' => 'Provider is not a hosting provider']);

        self::assertNotSame($nonHostingProvider->id, $deployment->refresh()->provider_id);
    }
}
