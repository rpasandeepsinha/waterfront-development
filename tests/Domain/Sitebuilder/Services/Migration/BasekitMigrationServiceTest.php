<?php

declare(strict_types=1);

namespace Tests\Domain\Sitebuilder\Services\Migration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Factories\BasekitSitebuilderDeploymentFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\Factories\SitebuilderDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\TestCase;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitContext;
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitSitebuilderDeployment;
use Waterfront\Domain\Provision\Sitebuilder\Models\SitebuilderDeployment;
use Waterfront\Domain\Sitebuilder\Services\BasekitMigrationService;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(BasekitMigrationService::class)]
class BasekitMigrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private const string SCRIPT_SLUG = 'migrate-basekit-deployments';

    #[Test]
    public function migratesEligibleSubscriptionAndCreatesAllExpectedRows(): void
    {
        $subscription = $this->makeEligibleSubscription(
            domain: 'migrateme.example',
            packageReference: 7,
            siteRef: 777,
            userRef: 888,
            contractPeriod: 12
        );

        /** @var BasekitMigrationService $service */
        $service = $this->app->make(BasekitMigrationService::class);

        $service->migrate(self::SCRIPT_SLUG, $subscription->uuid);

        $sitebuilderDeployment = SitebuilderDeployment::query()->latest('id')->first();
        self::assertInstanceOf(SitebuilderDeployment::class, $sitebuilderDeployment, 'SitebuilderDeployment should be created');

        $originRequest = $sitebuilderDeployment->originRequest;
        self::assertNotNull($originRequest, 'SitebuilderDeployment must link to an origin ProvisioningRequest');
        self::assertNotNull($originRequest->context_uuid);
        self::assertEquals($originRequest->context_uuid->toString(), $subscription->uuid);

        $payload = json_decode($originRequest->request_data, true);

        self::assertIsArray($payload);
        self::assertSame('migrateme.example', $payload['domain']);
        self::assertSame([7], $payload['packages']);
        self::assertSame('Test', $payload['firstname']);
        self::assertSame('Kees', $payload['lastname']);
        self::assertSame('testkees@example.com', $payload['email']);
        self::assertSame(12, $payload['contractPeriod']);
        self::assertTrue(Uuid::isValid((string) $originRequest->context_uuid));

        /** @var BasekitSitebuilderDeployment|null $basekitSitebuilderDeployment */
        $basekitSitebuilderDeployment = BasekitSitebuilderDeployment::query()
            ->where('sitebuilder_deployment_id', $sitebuilderDeployment->id)
            ->first();

        self::assertNotNull($basekitSitebuilderDeployment, 'BasekitSitebuilderDeployment must be created when site_ref present');
        self::assertSame(777, $basekitSitebuilderDeployment->site_ref);

        /** @var BasekitContext|null $context */
        $context = BasekitContext::query()
            ->where('context_uuid', $originRequest->context_uuid)
            ->first();

        self::assertNotNull($context, 'Basekit context must be created when user_ref present');
        self::assertSame(888, $context->user_ref);
    }

    #[Test]
    public function skipsWhenAlreadyMigrated(): void
    {
        $subscription = $this->makeEligibleSubscription(
            domain: 'already.example',
            packageReference: 5,
            siteRef: 100,
            userRef: 200
        );

        $provisioningRequest = ProvisioningRequestFactory::new()
            ->sitebuilder()
            ->state(['tag' => $subscription->uuid])
            ->createOne();

        $existingSitebuilder = SitebuilderDeploymentFactory::new()
            ->state(['origin_provisioning_request_id' => $provisioningRequest->id])
            ->createOne();

        BasekitSitebuilderDeploymentFactory::new()
            ->state([
                'sitebuilder_deployment_id' => $existingSitebuilder->id,
                'site_ref' => 100,
            ])
            ->createOne();

        /** @var BasekitMigrationService $basekitMigrationService */
        $basekitMigrationService = $this->app->make(BasekitMigrationService::class);
        $basekitMigrationService->migrate(self::SCRIPT_SLUG, $subscription->uuid);

        self::assertSame(1, SitebuilderDeployment::query()->count());
    }

    #[Test]
    public function skipsWhenNoPackageReference(): void
    {
        $subscription = $this->makeEligibleSubscription(
            domain: 'nopkg.example',
            packageReference: null,
            siteRef: 1,
            userRef: 2
        );

        /** @var BasekitMigrationService $basekitMigrationService */
        $basekitMigrationService = $this->app->make(BasekitMigrationService::class);
        $basekitMigrationService->migrate(self::SCRIPT_SLUG, $subscription->uuid);

        self::assertDatabaseCount(new SitebuilderDeployment()->getTable(), 0);
        self::assertDatabaseCount(new BasekitSitebuilderDeployment()->getTable(), 0);
        self::assertDatabaseCount(new BasekitContext()->getTable(), 0);
    }

    #[Test]
    public function skipsWhenNoSiteRef(): void
    {
        $subscription = $this->makeEligibleSubscription(
            domain: 'nosite.example',
            packageReference: 11,
            siteRef: null,
            userRef: 999
        );

        /** @var BasekitMigrationService $basekitmigrationService */
        $basekitmigrationService = $this->app->make(BasekitMigrationService::class);
        $basekitmigrationService->migrate(self::SCRIPT_SLUG, $subscription->uuid);

        self::assertDatabaseCount(new SitebuilderDeployment()->getTable(), 0);
        self::assertDatabaseCount(new BasekitSitebuilderDeployment()->getTable(), 0);
        self::assertDatabaseCount(new BasekitContext()->getTable(), 0);
    }

    #[Test]
    public function skipsWhenNoUserRef(): void
    {
        $subscription = $this->makeEligibleSubscription(
            domain: 'nouser.example',
            packageReference: 11,
            siteRef: 1234,
            userRef: null
        );

        /** @var BasekitMigrationService $basekitMigrationService */
        $basekitMigrationService = $this->app->make(BasekitMigrationService::class);
        $basekitMigrationService->migrate(self::SCRIPT_SLUG, $subscription->uuid);

        self::assertDatabaseCount(new SitebuilderDeployment()->getTable(), 0);
        self::assertDatabaseCount(new BasekitSitebuilderDeployment()->getTable(), 0);
        self::assertDatabaseCount(new BasekitContext()->getTable(), 0);
    }

    #[Test]
    public function deleteHostingDeploymentSoftDelete(): void
    {
        $subscription = $this->makeEligibleSubscription(
            domain: 'todelete.example',
            packageReference: 11,
            siteRef: 123,
            userRef: 999
        );

        /** @var BasekitMigrationService $basekitmigrationService */
        $basekitmigrationService = $this->app->make(BasekitMigrationService::class);

        $basekitmigrationService->deleteSitebuilderHostingDeployment(self::SCRIPT_SLUG, $subscription->uuid);

        $hostingDeployment = $subscription->hostingDeployment()->withTrashed()->first();
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);

        $hostingDeployment->refresh();
        self::assertTrue($hostingDeployment->trashed(), 'HostingDeployment should be soft-deleted');
    }

    private function makeEligibleSubscription(
        string $domain,
        ?int $packageReference,
        ?int $siteRef,
        ?int $userRef,
        int $contractPeriod = 12
    ): Subscription {
        $hostingGroup = new ProductGroupFactory()->hosting()->createOne();

        $productFactory = ProductFactory::new()->siteBuilder($hostingGroup);

        if ($packageReference !== null) {
            $productFactory = $productFactory->has(
                ProductSpecFactory::new()->state([
                    'name' => ProductSpecName::BASEKIT_PACKAGE_REFERENCE->value,
                    'value' => $packageReference,
                ]),
                'productSpecs'
            );
        }

        $product = $productFactory->createOne();

        $customer = CustomerFactory::new()->createOne([
            'first_name' => 'Test',
            'last_name' => 'Kees',
            'email' => 'testkees@example.com',
        ]);

        $sitebuilderProvider = ProviderFactory::new()
            ->siteBuilderBaseKit()
            ->createOne();

        /** @var Subscription $subscription */
        $subscription = SubscriptionFactory::new()
            ->state(fn () => [
                'customer_id' => $customer->id,
                'product_uuid' => $product->uuid,
                'domain' => $domain,
                'contract_period' => $contractPeriod,
            ])
            ->createOne();

        HostingDeploymentFactory::new()
            ->state(fn () => [
                'subscription_uuid' => $subscription->uuid,
                'sitebuilder_provider_id' => $sitebuilderProvider->id,
                'basekit_site_ref' => $siteRef,
                'basekit_user_ref' => $userRef,
            ])
            ->createOne();

        self::assertSame(ProductGroupType::HOSTING, $product->productGroup->slug);

        return $subscription->refresh();
    }
}
