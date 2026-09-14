<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\DomainProviderController;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(DomainProviderController::class)]
class DomainProviderControllerTest extends IntegrationTestCase
{
    private const string DOMAIN = 'test-provider.nl';

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = CustomerFactory::new()->createOne();
        $product = ProductFactory::new()->nlDomain()->createOne();

        $this->subscription = SubscriptionFactory::new()->for($customer)->for($product)->createOne([
            'domain' => self::DOMAIN,
        ]);
    }

    #[Test]
    public function updateProviderOnDomainDeploymentSuccessfully(): void
    {
        $oldProvider = ProviderFactory::new()->domainOpenProvider()->createOne();
        $newProvider = ProviderFactory::new()->domainRtr()->createOne();

        DomainDeploymentFactory::new()->for($oldProvider)->createOne([
            'subscription_uuid' => $this->subscription->uuid,
        ]);

        $this->actingAsEmployee()
            ->patchJson(
                $this->generateRoute('admin.domain.provider.update', ['domain' => self::DOMAIN]),
                ['provider' => ProviderSlug::REALTIME_REGISTER->value],
            )
            ->assertNoContent();

        $this->subscription->refresh();
        self::assertNotNull($this->subscription->domainDeployment);
        self::assertSame($newProvider->id, $this->subscription->domainDeployment->provider_id);
    }

    #[Test]
    public function updateReturnsNotFoundForNonExistentDomain(): void
    {
        ProviderFactory::new()->domainRtr()->createOne();

        $this->actingAsEmployee()
            ->patchJson(
                $this->generateRoute('admin.domain.provider.update', ['domain' => 'non-existent-domain.nl']),
                ['provider' => ProviderSlug::REALTIME_REGISTER->value],
            )
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    #[Test]
    public function updateReturnsNotFoundWhenDomainHasNoDeployment(): void
    {
        ProviderFactory::new()->domainRtr()->createOne();

        $this->actingAsEmployee()
            ->patchJson(
                $this->generateRoute('admin.domain.provider.update', ['domain' => self::DOMAIN]),
                ['provider' => ProviderSlug::REALTIME_REGISTER->value],
            )
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    #[Test]
    public function updateReturnsUnprocessableEntityForInvalidProviderSlug(): void
    {
        DomainDeploymentFactory::new()->withOpenProvider()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
        ]);

        $this->actingAsEmployee()
            ->patchJson(
                $this->generateRoute('admin.domain.provider.update', ['domain' => self::DOMAIN]),
                ['provider' => 'non_existent_provider'],
            )
            ->assertUnprocessable()
            ->assertJsonStructure(['message']);
    }

    #[Test]
    public function updateReturnsUnprocessableEntityForDisabledProvider(): void
    {
        ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::REALTIME_REGISTER,
            'enabled' => false,
            'default' => false,
        ]);

        DomainDeploymentFactory::new()->withOpenProvider()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
        ]);

        $this->actingAsEmployee()
            ->patchJson(
                $this->generateRoute('admin.domain.provider.update', ['domain' => self::DOMAIN]),
                ['provider' => ProviderSlug::REALTIME_REGISTER->value],
            )
            ->assertUnprocessable()
            ->assertJsonStructure(['message']);
    }

    #[Test]
    public function updateReturnsUnprocessableEntityForNonDomainProvider(): void
    {
        ProviderFactory::new()->sslRtr()->createOne();

        DomainDeploymentFactory::new()->withOpenProvider()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
        ]);

        $this->actingAsEmployee()
            ->patchJson(
                $this->generateRoute('admin.domain.provider.update', ['domain' => self::DOMAIN]),
                ['provider' => ProviderSlug::REALTIME_REGISTER->value],
            )
            ->assertUnprocessable()
            ->assertJsonStructure(['message']);
    }

    #[Test]
    public function updateReturnsValidationErrorWhenProviderFieldIsMissing(): void
    {
        $this->actingAsEmployee()
            ->patchJson(
                $this->generateRoute('admin.domain.provider.update', ['domain' => self::DOMAIN]),
                [],
            )
            ->assertUnprocessable();
    }
}
