<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Provision\DTO\ProvisioningFilteredResult;
use Waterfront\Domain\Provision\DTO\ProvisioningResultQueryFilters;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Sitebuilder\Requests\TerminateSitebuilderContextRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\UpdateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Results\SitebuilderResult;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Sitebuilder\Services\BaseKitService;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Exceptions\TerminateException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Subscriptions\Services\DeprovisionService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionTerminateService;

#[CoversClass(SubscriptionTerminateService::class)]
#[AllowMockObjectsWithoutExpectations]
class SubscriptionTerminateServiceTest extends IntegrationTestCase
{
    private DeprovisionService&MockObject $deprovisionService;

    private SubscriptionTerminateService $subscriptionTerminateService;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deprovisionService = self::createMock(DeprovisionService::class);

        $this->subscriptionTerminateService = new SubscriptionTerminateService(
            self::resolve(LoggerInterface::class),
            self::resolve(MailerInterface::class),
            self::resolve(SubscriptionRepository::class),
            $this->deprovisionService,
            self::resolve(ProvisionGateway::class),
        );

        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function terminateSubscription(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->extension()))
            ->createOne([
                'administrative_status' => AdministrativeStatus::CANCELED->value,
                'end_date' => CarbonImmutable::yesterday(),
            ]);

        $this->deprovisionService->expects(self::once())->method('deprovision');

        $this->subscriptionTerminateService->terminate($subscription);

        self::assertSame(AdministrativeStatus::ARCHIVED->value, $subscription->administrative_status);
    }

    #[Test]
    public function terminateSubscriptionWithChild(): void
    {
        $group = new ProductGroupFactory()->extension()->createOne();
        $parentSubscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for($group))
            ->createOne([
                'administrative_status' => AdministrativeStatus::CANCELED->value,
                'end_date' => CarbonImmutable::yesterday(),
            ]);
        $childSubscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for($group))
            ->createOne([
                'parent_subscription_id' => $parentSubscription->id,
                'administrative_status' => AdministrativeStatus::CANCELED->value,
                'end_date' => CarbonImmutable::yesterday(),
            ]);

        $this->deprovisionService
            ->expects(self::exactly(2))
            ->method('deprovision')
            ->with(
                ...self::withConsecutive(
                    [self::callback(
                        fn (Subscription $subscription): bool => $subscription->id === $childSubscription->id,
                    )],
                    [self::callback(
                        fn (Subscription $subscription): bool => $subscription->id === $parentSubscription->id,
                    )],
                ),
            );

        $this->subscriptionTerminateService->terminate($parentSubscription);

        self::assertSame(AdministrativeStatus::ARCHIVED->value, $parentSubscription->administrative_status);
        self::assertSame(AdministrativeStatus::ARCHIVED->value, $childSubscription->refresh()->administrative_status);
    }

    #[Test]
    public function terminateChildNotAllowed(): void
    {
        $group = new ProductGroupFactory()->extension()->createOne();
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for($group))
            ->createOne([
                'administrative_status' => AdministrativeStatus::CANCELED->value,
                'end_date' => CarbonImmutable::yesterday(),
            ]);
        $childSubscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for($group))
            ->createOne([
                'parent_subscription_id' => $subscription->id,
                'administrative_status' => AdministrativeStatus::CANCELED->value,
                'end_date' => CarbonImmutable::yesterday(),
            ]);

        self::expectException(TerminateException::class);

        $this->subscriptionTerminateService->terminate($childSubscription);

        self::assertSame(AdministrativeStatus::CANCELED->value, $subscription->administrative_status);
        self::assertSame(AdministrativeStatus::CANCELED->value, $childSubscription->refresh()->administrative_status);
    }

    #[Test]
    public function terminateExpiredSubscriptionWithUnExpiredChildren(): void
    {
        $group = new ProductGroupFactory()->extension()->createOne();
        $parentSubscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for($group))
            ->createOne([
                'administrative_status' => AdministrativeStatus::CANCELED->value,
                'end_date' => CarbonImmutable::yesterday(),
            ]);
        $childSubscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for($group))
            ->createOne([
                'parent_subscription_id' => $parentSubscription->id,
                'administrative_status' => AdministrativeStatus::CANCELED->value,
                'end_date' => CarbonImmutable::tomorrow(),
            ]);

        $this->deprovisionService->expects(self::exactly(2))->method('deprovision');

        $this->subscriptionTerminateService->terminate($parentSubscription);

        self::assertSame(AdministrativeStatus::ARCHIVED->value, $parentSubscription->administrative_status);
        self::assertSame(AdministrativeStatus::ARCHIVED->value, $childSubscription->refresh()->administrative_status);
    }

    #[Test]
    public function terminateNotExpiredSubscriptionWithExpiredChild(): void
    {
        $group = new ProductGroupFactory()->extension()->createOne();
        $parentSubscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for($group))
            ->createOne([
                'administrative_status' => AdministrativeStatus::CANCELED->value,
                'end_date' => CarbonImmutable::tomorrow(),
            ]);
        $childSubscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for($group))
            ->createOne([
                'parent_subscription_id' => $parentSubscription->id,
                'administrative_status' => AdministrativeStatus::CANCELED->value,
                'end_date' => CarbonImmutable::yesterday(),
            ]);

        $this->deprovisionService
            ->expects(self::once())
            ->method('deprovision')
            ->with(
                self::callback(fn (Subscription $subscription): bool => $subscription->id === $childSubscription->id),
            );

        $this->subscriptionTerminateService->terminate($parentSubscription);

        self::assertSame(AdministrativeStatus::CANCELED->value, $parentSubscription->administrative_status);
        self::assertSame(AdministrativeStatus::ARCHIVED->value, $childSubscription->refresh()->administrative_status);
    }

    #[Test]
    public function terminateM365Subscription(): void
    {
        $group = new ProductGroupFactory()->microsoft365()->createOne();
        $parentSubscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for($group))
            ->createOne([
                'administrative_status' => AdministrativeStatus::CANCELED->value,
                'end_date' => CarbonImmutable::yesterday(),
            ]);
        $childSubscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for($group))
            ->createOne([
                'parent_subscription_id' => $parentSubscription->id,
                'administrative_status' => AdministrativeStatus::CANCELED->value,
                'end_date' => CarbonImmutable::yesterday(),
            ]);

        $this->deprovisionService
            ->expects(self::once())
            ->method('deprovision')
            ->with(
                self::callback(fn (Subscription $subscription): bool => $subscription->id === $parentSubscription->id),
            );

        $this->subscriptionTerminateService->terminate($parentSubscription);

        self::assertSame(AdministrativeStatus::CANCELED->value, $parentSubscription->administrative_status);
        self::assertSame(AdministrativeStatus::CANCELED->value, $childSubscription->refresh()->administrative_status);
    }

    #[Test]
    public function terminateSitebuilderSubscriptionWithProvisioningRequests(): void
    {
        $group = new ProductGroupFactory()->createOne(['slug' => ProductGroupType::HOSTING]);
        $product = new ProductFactory()
            ->siteBuilder($group)
            ->createOne();
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->administrativeStatusCancelled()
            ->createOne([
                'contract_period' => 12,
                'domain' => 'example.com',
                'product_uuid' => $product->uuid,
                'end_date' => new CarbonImmutable(),
            ]);

        new ServerFactory()->createOne(['type' => ServerType::DIRECTADMIN]);
        $siteBuilderServer = new ServerFactory()->createOne(['type' => ServerType::SITEBUILDER]);

        $sitebuilderProvider = ProviderFactory::new()->createOne([
            'type' => ProviderType::SITEBUILDER,
            'slug' => ProviderSlug::BASEKIT,
            'enabled' => true,
            'default' => true,
        ]);

        $directAdminMailProvider = ProviderFactory::new()->createOne([
            'type' => ProviderType::MAILONLY,
            'slug' => ProviderSlug::DIRECTADMIN,
            'enabled' => true,
            'default' => true,
        ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'basekit_server_id' => $siteBuilderServer->id,
            'mail_only_server_id' => $siteBuilderServer->id,
            'sitebuilder_provider_id' => $sitebuilderProvider->id,
            'mail_only_provider_id' => $directAdminMailProvider->id,
            'basekit_site_ref' => 69,
            'directadmin_customer_username' => 'mail',
        ]);

        $provRequest = new ProvisioningRequestFactory()
            ->sitebuilder()
            ->createOne([
                'tag' => $subscription->uuid,
                'request_type' => ProvisionType::SITEBUILDER,
                'request_name' => ProvisionRequestName::CREATE_SITEBUILDER,
            ]);

        $sitebuilderMock = $this->createMock(BaseKitService::class);
        $sitebuilderMock->expects(self::never())->method('deleteSite');
        $this->app->bind(BaseKitService::class, fn () => $sitebuilderMock);

        $sidebuilderRequestResult = new SitebuilderResult(
            provisionData: self::createStub(ProvisionRequestInterface::class),
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $request = new TerminateSitebuilderContextRequest($provRequest->uuid);
        $expectedCreateRequest = new ProvisioningFilteredResult(
            resultId: 1,
            uuid: Uuid::uuid4(),
            tag: Uuid::fromString($subscription->uuid),
            context: null,
            response: 'response',
            status: ProvisionStatus::SUCCESS,
            createdAt: CarbonImmutable::now(),
            requestCreatedAt: CarbonImmutable::now(),
            requestUpdatedAt: null,
            requestData: '{}',
            requestUuid: Uuid::uuid4(),
            requestName: ProvisionRequestName::CREATE_SITEBUILDER,
            requestType: ProvisionType::SITEBUILDER,
            provider: ProvisionProvider::BASEKIT,
        );

        $provisionGatewayMock = $this->createMock(ProvisionGateway::class);
        $provisionGatewayMock
            ->expects(self::once())
            ->method('fetch')
            ->with(
                self::callback(
                    fn (
                        ProvisioningResultQueryFilters $filters,
                    ) => (
                        $filters->requestType === ProvisionType::SITEBUILDER
                        && $filters->tag?->toString() === $subscription->uuid
                    ),
                ),
            )
            ->willReturn(new Collection([$expectedCreateRequest]));
        $provisionGatewayMock
            ->expects(self::once())
            ->method('request')
            ->with(
                self::callback(
                    fn ($request) => (
                        $request->context->toString() === $subscription->uuid
                        && $request->tag->toString() === $subscription->uuid
                    ),
                ),
            )
            ->willReturn($sidebuilderRequestResult);
        $this->app->bind(ProvisionGateway::class, fn () => $provisionGatewayMock);

        $service = self::resolve(SubscriptionTerminateService::class);
        $service->terminate($subscription);
    }

    #[Test]
    public function terminateSiteBuilderAddonsCallsProvisoningGateway(): void
    {
        $hostingGroup = new ProductGroupFactory()->hosting()->createOne();
        $addonGroup = new ProductGroupFactory()->addon()->createOne();
        $baseSitebuilder = new ProductFactory()->for($hostingGroup)->createOne([
            'slug' => 'sitebuilder',
        ]);
        $randomAddon1 = new ProductFactory()->for($addonGroup)->createOne([
            'slug' => 'xxxxx',
        ]);
        $randomAddon2 = new ProductFactory()->for($addonGroup)->createOne([
            'slug' => 'yyyyy',
        ]);

        $productSpec = new ProductSpec();
        $productSpec->name = ProductSpecName::BASEKIT_PACKAGE_REFERENCE->value;
        $productSpec->value = 0001;
        $productSpec->product_id = $baseSitebuilder->id;
        $productSpec->save();

        $productSpec = new ProductSpec();
        $productSpec->name = ProductSpecName::BASEKIT_PACKAGE_REFERENCE->value;
        $productSpec->value = 6969;
        $productSpec->product_id = $randomAddon2->id;
        $productSpec->save();

        $productSpec = new ProductSpec();
        $productSpec->name = ProductSpecName::BASEKIT_PACKAGE_REFERENCE->value;
        $productSpec->value = 0420;
        $productSpec->product_id = $randomAddon1->id;
        $productSpec->save();

        $subscription = new SubscriptionFactory()
            ->for($baseSitebuilder)
            ->for($this->customer)
            ->administrativeStatusExpired()
            ->createOne([
                'technical_status' => TechnicalStatus::OK->value,
                'end_date' => CarbonImmutable::yesterday(),
                'termination_date' => CarbonImmutable::yesterday(),
            ]);
        new SubscriptionFactory()
            ->for($randomAddon1)
            ->for($this->customer)
            ->administrativeStatusExpired()
            ->createOne([
                'technical_status' => TechnicalStatus::OK->value,
                'parent_subscription_id' => $subscription->id,
                'end_date' => CarbonImmutable::yesterday(),
                'termination_date' => CarbonImmutable::yesterday(),
            ]);
        new SubscriptionFactory()
            ->for($randomAddon2)
            ->for($this->customer)
            ->administrativeStatusExpired()
            ->createOne([
                'technical_status' => TechnicalStatus::OK->value,
                'parent_subscription_id' => $subscription->id,
                'end_date' => CarbonImmutable::yesterday(),
                'termination_date' => CarbonImmutable::yesterday(),
            ]);

        new ProvisioningRequestFactory()
            ->sitebuilder()
            ->createOne([
                'tag' => $subscription->uuid,
                'request_type' => ProvisionType::SITEBUILDER,
                'request_name' => ProvisionRequestName::CREATE_SITEBUILDER,
            ]);

        $sidebuilderRequestResult = new SitebuilderResult(
            provisionData: self::createStub(ProvisionRequestInterface::class),
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $expectedCreateRequest = new ProvisioningFilteredResult(
            resultId: 1,
            uuid: Uuid::uuid4(),
            tag: Uuid::fromString($subscription->uuid),
            context: null,
            response: 'response',
            status: ProvisionStatus::SUCCESS,
            createdAt: CarbonImmutable::now(),
            requestCreatedAt: CarbonImmutable::now(),
            requestUpdatedAt: null,
            requestData: '{}',
            requestUuid: Uuid::uuid4(),
            requestName: ProvisionRequestName::CREATE_SITEBUILDER,
            requestType: ProvisionType::SITEBUILDER,
            provider: ProvisionProvider::BASEKIT,
        );

        $provisionGatewayMock = $this->createMock(ProvisionGateway::class);
        $provisionGatewayMock
            ->expects(self::once())
            ->method('fetch')
            ->with(
                self::callback(
                    fn (
                        ProvisioningResultQueryFilters $filters,
                    ) => (
                        $filters->requestType === ProvisionType::SITEBUILDER
                        && $filters->tag?->toString() === $subscription->uuid
                    ),
                ),
            )
            ->willReturn(new Collection([$expectedCreateRequest]));
        $provisionGatewayMock->expects(self::exactly(1))->method('request')->willReturn($sidebuilderRequestResult);

        $subscriptionService = new SubscriptionTerminateService(
            self::createMock(LoggerInterface::class),
            self::createMock(MailerInterface::class),
            self::resolve(SubscriptionRepository::class),
            self::createMock(DeprovisionService::class),
            $provisionGatewayMock,
        );

        $subscriptionService->terminate($subscription);
    }

    #[Test]
    public function terminateSiteBuilderAddonsCallsProvisoningGatewayWithParentAndRemainingChildSpecInRequest(): void
    {
        Model::preventLazyLoading(false);

        $hostingGroup = new ProductGroupFactory()->hosting()->createOne();
        $addonGroup = new ProductGroupFactory()->addon()->createOne();
        $baseSitebuilder = new ProductFactory()->for($hostingGroup)->createOne([
            'slug' => 'sitebuilder',
        ]);
        $randomAddon1 = new ProductFactory()->for($addonGroup)->createOne([
            'slug' => 'addon',
        ]);
        $randomAddon2 = new ProductFactory()->for($addonGroup)->createOne([
            'slug' => 'addon2',
        ]);

        $productSpec = new ProductSpec();
        $productSpec->name = ProductSpecName::BASEKIT_PACKAGE_REFERENCE->value;
        $productSpec->value = 001;
        $productSpec->product_id = $baseSitebuilder->id;
        $productSpec->save();

        $productSpec = new ProductSpec();
        $productSpec->name = ProductSpecName::BASEKIT_PACKAGE_REFERENCE->value;
        $productSpec->value = 420;
        $productSpec->product_id = $randomAddon1->id;
        $productSpec->save();

        $productSpec = new ProductSpec();
        $productSpec->name = ProductSpecName::BASEKIT_PACKAGE_REFERENCE->value;
        $productSpec->value = 6969;
        $productSpec->product_id = $randomAddon2->id;
        $productSpec->save();

        $subscription = new SubscriptionFactory()
            ->for($baseSitebuilder)
            ->for($this->customer)
            ->createOne([
                'technical_status' => TechnicalStatus::OK->value,
            ]);
        new SubscriptionFactory()
            ->for($randomAddon1)
            ->for($this->customer)
            ->createOne([
                'technical_status' => TechnicalStatus::OK->value,
                'parent_subscription_id' => $subscription->id,
            ]);

        new SubscriptionFactory()
            ->for($randomAddon2)
            ->for($this->customer)
            ->administrativeStatusExpired()
            ->createOne([
                'technical_status' => TechnicalStatus::OK->value,
                'parent_subscription_id' => $subscription->id,
                'end_date' => CarbonImmutable::yesterday(),
                'termination_date' => CarbonImmutable::yesterday(),
            ]);

        $request = new ProvisioningRequestFactory()
            ->sitebuilder()
            ->createOne([
                'tag' => $subscription->uuid,
                'request_type' => ProvisionType::SITEBUILDER,
                'request_name' => ProvisionRequestName::CREATE_SITEBUILDER,
            ]);

        $request->refresh();

        $sidebuilderRequestResult = new SitebuilderResult(
            provisionData: self::createStub(ProvisionRequestInterface::class),
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $request = new UpdateSitebuilderRequest(
            tagUuid: Uuid::fromString($subscription->uuid),
            context: Uuid::fromString($subscription->uuid),
            packages: [0001, 420],
            contractPeriod: $subscription->contract_period,
        );
        $expectedCreateRequest = new ProvisioningFilteredResult(
            resultId: 1,
            uuid: Uuid::uuid4(),
            tag: Uuid::fromString($subscription->uuid),
            context: null,
            response: 'response',
            status: ProvisionStatus::SUCCESS,
            createdAt: CarbonImmutable::now(),
            requestCreatedAt: CarbonImmutable::now(),
            requestUpdatedAt: null,
            requestData: '{}',
            requestUuid: Uuid::uuid4(),
            requestName: ProvisionRequestName::CREATE_SITEBUILDER,
            requestType: ProvisionType::SITEBUILDER,
            provider: ProvisionProvider::BASEKIT,
        );

        $provisionGatewayMock = $this->createMock(ProvisionGateway::class);
        $provisionGatewayMock
            ->expects(self::once())
            ->method('fetch')
            ->with(
                self::callback(
                    fn (
                        ProvisioningResultQueryFilters $filters,
                    ) => (
                        $filters->requestType === ProvisionType::SITEBUILDER
                        && $filters->tag?->toString() === $subscription->uuid
                    ),
                ),
            )
            ->willReturn(new Collection([$expectedCreateRequest]));
        $provisionGatewayMock
            ->expects(self::exactly(1))
            ->method('request')
            ->with($request)
            ->willReturn(
                $sidebuilderRequestResult,
            );

        $subscriptionService = new SubscriptionTerminateService(
            self::createMock(LoggerInterface::class),
            self::createMock(MailerInterface::class),
            self::resolve(SubscriptionRepository::class),
            self::createMock(DeprovisionService::class),
            $provisionGatewayMock,
        );

        $subscriptionService->terminate($subscription);
    }

    #[Test]
    public function terminateSiteBuilderAddonsDoesntCallGatewayBecauseParentHasNoSpec(): void
    {
        $hostingGroup = new ProductGroupFactory()->hosting()->createOne();
        $addonGroup = new ProductGroupFactory()->addon()->createOne();
        $baseSitebuilder = new ProductFactory()->for($hostingGroup)->createOne([
            'slug' => 'sitebuilder',
        ]);
        $randomAddon1 = new ProductFactory()->for($addonGroup)->createOne([
            'slug' => 'addon',
        ]);
        $randomAddon2 = new ProductFactory()->for($addonGroup)->createOne([
            'slug' => 'addon2',
        ]);

        $productSpec = new ProductSpec();
        $productSpec->name = ProductSpecName::BASEKIT_PACKAGE_REFERENCE->value;
        $productSpec->value = 420;
        $productSpec->product_id = $randomAddon1->id;
        $productSpec->save();

        $productSpec = new ProductSpec();
        $productSpec->name = ProductSpecName::BASEKIT_PACKAGE_REFERENCE->value;
        $productSpec->value = 6969;
        $productSpec->product_id = $randomAddon2->id;
        $productSpec->save();

        $subscription = new SubscriptionFactory()
            ->for($baseSitebuilder)
            ->for($this->customer)
            ->createOne([
                'technical_status' => TechnicalStatus::OK->value,
            ]);
        new SubscriptionFactory()
            ->for($randomAddon1)
            ->for($this->customer)
            ->createOne([
                'technical_status' => TechnicalStatus::OK->value,
                'parent_subscription_id' => $subscription->id,
            ]);

        new SubscriptionFactory()
            ->for($randomAddon2)
            ->for($this->customer)
            ->administrativeStatusExpired()
            ->createOne([
                'technical_status' => TechnicalStatus::OK->value,
                'parent_subscription_id' => $subscription->id,
                'end_date' => CarbonImmutable::yesterday(),
                'termination_date' => CarbonImmutable::yesterday(),
            ]);

        $provisionGatewayMock = $this->createMock(ProvisionGateway::class);
        $provisionGatewayMock->expects(self::never())->method('request');

        $subscriptionService = new SubscriptionTerminateService(
            self::createMock(LoggerInterface::class),
            self::createMock(MailerInterface::class),
            self::resolve(SubscriptionRepository::class),
            self::createMock(DeprovisionService::class),
            $provisionGatewayMock,
        );

        $subscriptionService->terminate($subscription);
    }
}
