<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\DomainProviderBusinessUnitFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\DomainBusinessUnitController;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(DomainBusinessUnitController::class)]
class DomainBusinessUnitControllerTest extends IntegrationTestCase
{
    private const string DOMAIN = 'test-business-unit.nl';

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
    public function indexReturnsAllBusinessUnitsOrderedByName(): void
    {
        DomainProviderBusinessUnitFactory::new()->waterfront()->createOne();
        DomainProviderBusinessUnitFactory::new()->argeweb()->createOne();

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.domain.business-units'))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    ['slug' => 'argeweb', 'name' => 'Argeweb'],
                    ['slug' => 'waterfront', 'name' => 'Waterfront'],
                ],
            ]);
    }

    #[Test]
    public function updateSetsBusinessUnitOnDomainDeployment(): void
    {
        $businessUnit = DomainProviderBusinessUnitFactory::new()->argeweb()->createOne();

        $domainDeployment = DomainDeploymentFactory::new()->withOpenProvider()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'domain_business_unit_id' => null,
        ]);

        $this->actingAsEmployee()
            ->patchJson(
                $this->generateRoute('admin.domain.business-unit.update', ['domain' => self::DOMAIN]),
                ['business_unit' => 'argeweb'],
            )
            ->assertOk()
            ->assertJsonStructure(['message', 'errors']);

        $domainDeployment->refresh();
        self::assertSame($businessUnit->id, $domainDeployment->domain_business_unit_id);
    }

    #[Test]
    public function updateWithNullBusinessUnitResetsTheDomainDeployment(): void
    {
        $businessUnit = DomainProviderBusinessUnitFactory::new()->argeweb()->createOne();

        $domainDeployment = DomainDeploymentFactory::new()->withOpenProvider()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'domain_business_unit_id' => $businessUnit->id,
        ]);

        $this->actingAsEmployee()
            ->patchJson(
                $this->generateRoute('admin.domain.business-unit.update', ['domain' => self::DOMAIN]),
                ['business_unit' => null],
            )
            ->assertOk();

        $domainDeployment->refresh();
        self::assertNull($domainDeployment->domain_business_unit_id);
    }

    #[Test]
    public function updateReturnsUnprocessableEntityForUnknownBusinessUnit(): void
    {
        $businessUnit = DomainProviderBusinessUnitFactory::new()->waterfront()->createOne();

        $domainDeployment = DomainDeploymentFactory::new()->withOpenProvider()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'domain_business_unit_id' => $businessUnit->id,
        ]);

        $this->actingAsEmployee()
            ->patchJson(
                $this->generateRoute('admin.domain.business-unit.update', ['domain' => self::DOMAIN]),
                ['business_unit' => 'does-not-exist'],
            )
            ->assertUnprocessable()
            ->assertJsonStructure(['message']);

        $domainDeployment->refresh();
        self::assertSame($businessUnit->id, $domainDeployment->domain_business_unit_id);
    }

    #[Test]
    public function updateReturnsNotFoundForNonExistentDomain(): void
    {
        DomainProviderBusinessUnitFactory::new()->argeweb()->createOne();

        $this->actingAsEmployee()
            ->patchJson(
                $this->generateRoute('admin.domain.business-unit.update', ['domain' => 'non-existent-domain.nl']),
                ['business_unit' => 'argeweb'],
            )
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    #[Test]
    public function updateReturnsNotFoundWhenDomainHasNoDeployment(): void
    {
        DomainProviderBusinessUnitFactory::new()->argeweb()->createOne();

        $this->actingAsEmployee()
            ->patchJson(
                $this->generateRoute('admin.domain.business-unit.update', ['domain' => self::DOMAIN]),
                ['business_unit' => 'argeweb'],
            )
            ->assertNotFound()
            ->assertJsonStructure(['message']);

        self::assertSame(0, DomainDeployment::query()->count());
    }

    #[Test]
    public function updateReturnsValidationErrorWhenBusinessUnitFieldIsMissing(): void
    {
        $this->actingAsEmployee()
            ->patchJson(
                $this->generateRoute('admin.domain.business-unit.update', ['domain' => self::DOMAIN]),
                [],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['business_unit']);
    }
}
