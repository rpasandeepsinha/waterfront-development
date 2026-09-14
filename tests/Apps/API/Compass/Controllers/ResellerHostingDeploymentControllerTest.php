<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ResellerHostingDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\ResellerHostingDeploymentController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Providers\Enums\ProviderSlug;

#[CoversClass(ResellerHostingDeploymentController::class)]
class ResellerHostingDeploymentControllerTest extends IntegrationTestCase
{
    public const string DOMAIN = 'test.nl';

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function showResellerHostingDeployment(): void
    {
        $resellerHostingProduct = new ProductFactory()->resellerHostingBrons()->createOne();
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($resellerHostingProduct)
            ->createOne(['domain' => self::DOMAIN]);

        $provider = new ProviderFactory()->hostingDirectAdmin()->createOne();
        $deployment = new ResellerHostingDeploymentFactory()->for($provider)->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);

        $response = $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.subscriptions.subscription.reseller-hosting-deployment', [
                'subscription' => $subscription->id,
            ]))
            ->assertOk();

        $content = $response->json();

        self::assertIsArray($content);
        self::assertSame($deployment->id, $content['id']);
        self::assertSame($subscription->uuid, $content['administrative_subscription_uuid']);
        self::assertSame(self::DOMAIN, $content['domain']);
        self::assertSame(ProviderSlug::DIRECTADMIN->value, $content['provider']);
        self::assertSame($deployment->server?->hostname, $content['server_name']);
        self::assertSame($deployment->relevant_username, $content['username']);
    }

    #[Test]
    public function showResellerHostingDeploymentNotFound(): void
    {
        $resellerHostingProduct = new ProductFactory()->resellerHostingBrons()->createOne();
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($resellerHostingProduct)
            ->createOne(['domain' => self::DOMAIN]);

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.subscriptions.subscription.reseller-hosting-deployment', [
                'subscription' => $subscription->id,
            ]))
            ->assertServerError()
            ->assertExactJson(['message' => 'The deployment could not be found']);
    }
}
