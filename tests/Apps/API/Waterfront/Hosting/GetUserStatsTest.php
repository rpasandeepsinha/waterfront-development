<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Hosting;

use PHPUnit\Framework\Attributes\CoversNothing;
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
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;

#[CoversNothing]
class GetUserStatsTest extends IntegrationTestCase
{
    private Customer $customer;

    private HostingDeployment $hostingDeployment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $subscription = new SubscriptionFactory()
            ->for(
                new ProductFactory()->for(
                    new ProductGroupFactory()->hosting(),
                )->createOne(),
            )
            ->for($this->customer)
            ->createOne();

        $this->hostingDeployment = new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'server_id' => new ServerFactory()->directadmin(),
            'provider_id' => new ProviderFactory()->createOne([
                'type' => ProviderType::HOSTING,
                'slug' => ProviderSlug::DIRECTADMIN,
                'enabled' => true,
                'default' => true,
            ]),
        ]);
    }

    #[Test]
    public function noProviderOnDeploymentThrowsError(): void
    {
        $this->hostingDeployment->update(['provider_id' => null]);
        $this->hostingDeployment->refresh();

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute(
                    'partners.hosting.get_user_stats',
                    $this->hostingDeployment->subscription_uuid,
                ),
            )
            ->assertServerError();
    }

    #[Test]
    public function directAdminProviderDoesNotThrowException(): void
    {
        $response = $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute(
                    'partners.hosting.get_user_stats',
                    $this->hostingDeployment->subscription_uuid,
                ),
            )
            ->assertOk();

        $content = (array) json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(2, $content['traffic']);
    }

    #[Test]
    public function directAdminProviderDoesNotThrowExceptionWithDeletedSubscription(): void
    {
        $this->hostingDeployment->subscription->update([
            'administrative_status' => AdministrativeStatus::ARCHIVED->value,
        ]);

        $response = $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute(
                    'partners.hosting.get_user_stats',
                    $this->hostingDeployment->subscription_uuid,
                ),
            )
            ->assertOk();

        $content = (array) json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(2, $content['traffic']);
    }

    #[Test]
    public function pleskThrowsNotImplementedException(): void
    {
        $pleskProvider = ProviderFactory::new()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::PLESK,
            'enabled' => true,
            'default' => true,
        ]);
        $this->hostingDeployment->update([
            'provider_id' => $pleskProvider->id,
        ]);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute(
                    'partners.hosting.get_user_stats',
                    $this->hostingDeployment->subscription_uuid,
                ),
            )
            ->assertServerError();
    }
}
