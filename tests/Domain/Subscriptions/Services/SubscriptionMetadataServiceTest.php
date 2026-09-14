<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Services;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\Identity;
use SandwaveIo\LighthouseAuthBase\Identity\Traits\IdentityTraits;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Lighthouse\DTO\IdentityMetadataDTO;
use Waterfront\Domain\Lighthouse\Exceptions\LighthouseException;
use Waterfront\Domain\Lighthouse\Exceptions\ResourceNotFoundException;
use Waterfront\Domain\Lighthouse\Services\LighthouseApiService;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCategory;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionCategories;
use Waterfront\Domain\Subscriptions\Services\SubscriptionMetadataService;

#[CoversClass(SubscriptionMetadataService::class)]
class SubscriptionMetadataServiceTest extends IntegrationTestCase
{
    private SubscriptionMetadataService $service;

    private LighthouseApiService&MockObject $lighthouseApiService;

    private Subscription $subscription;

    private SubscriptionCategories $subscriptionCategory;

    private UuidInterface $assigneeUuid;

    public function setUp(): void
    {
        parent::setUp();

        $this->lighthouseApiService = self::createMock(LighthouseApiService::class);
        $this->app->bind(LighthouseApiService::class, fn () => $this->lighthouseApiService);

        $this->service = self::resolve(SubscriptionMetadataService::class);

        $productGroup = new ProductGroupFactory()->extension()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();
        $this->subscription = new SubscriptionFactory()
            ->for(new CustomerFactory()->createOne())
            ->for($product)
            ->createOne();

        $this->subscriptionCategory = new SubscriptionCategories();
        $this->subscriptionCategory->subscription_id = $this->subscription->id;
        $this->subscriptionCategory->name = SubscriptionCategory::CUSTOMER_ACTION;
        $this->subscriptionCategory->save();

        $this->assigneeUuid = Uuid::uuid4();
    }

    #[Test]
    public function assignEmployeeCreatesNewCategoryWhenNoneExists(): void
    {
        $this->subscriptionCategory->delete();
        $this->subscription->unsetRelation('category');

        $identityUuid = Uuid::uuid4();
        $identity = new Identity(
            id: $identityUuid->toString(),
            schemaId: SchemaId::EMPLOYEE,
            schemaUrl: '',
            state: 'active',
            traits: new IdentityTraits('employee@sandwave.io', null),
            verifiableAddresses: [],
            recoveryAddresses: [],
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
            credentials: null,
            metadataPublic: null,
            metadataAdmin: null,
        );

        $this->lighthouseApiService
            ->expects(self::once())
            ->method('getKratosIdentityByIdentifier')
            ->with($this->assigneeUuid->toString())
            ->willReturn($identity);

        $this->service->assignEmployee($this->subscription, $this->assigneeUuid);

        $category = $this->subscription->refresh()->category;
        self::assertNotNull($category);
        self::assertNotNull($category->assignee_metadata);
        self::assertSame($identityUuid->toString(), $category->assignee_metadata->uuid->toString());
        self::assertSame('employee@sandwave.io', $category->assignee_metadata->email);
    }

    #[Test]
    public function assignEmployeeWithNullUuidClearsAssigneeMetadata(): void
    {
        $this->lighthouseApiService->expects(self::never())->method('getKratosIdentityByIdentifier');

        $this->service->assignEmployee($this->subscription, null);

        self::assertNull($this->subscriptionCategory->refresh()->assignee_metadata);
    }

    #[Test]
    public function assignEmployeeNoIdentityInLighthouse(): void
    {
        $this->lighthouseApiService
            ->expects(self::once())
            ->method('getKratosIdentityByIdentifier')
            ->with($this->assigneeUuid->toString())
            ->willThrowException(new ResourceNotFoundException());

        $this->expectException(RuntimeException::class);
        $this->service->assignEmployee($this->subscription, $this->assigneeUuid);
    }

    #[Test]
    public function assignEmployeeLighthouseExceptionIsHandled(): void
    {
        $this->lighthouseApiService
            ->expects(self::once())
            ->method('getKratosIdentityByIdentifier')
            ->with($this->assigneeUuid->toString())
            ->willThrowException(new LighthouseException('API error'));

        $this->expectException(RuntimeException::class);
        $this->service->assignEmployee($this->subscription, $this->assigneeUuid);
    }

    #[Test]
    public function assignEmployeeIdentityWithWrongSchema(): void
    {
        $identity = new Identity(
            id: Uuid::uuid4()->toString(),
            schemaId: SchemaId::CUSTOMER,
            schemaUrl: '',
            state: 'active',
            traits: new IdentityTraits('test@test.nl', null),
            verifiableAddresses: [],
            recoveryAddresses: [],
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
            credentials: null,
            metadataPublic: null,
            metadataAdmin: null,
        );

        $this->lighthouseApiService
            ->expects(self::once())
            ->method('getKratosIdentityByIdentifier')
            ->with($this->assigneeUuid->toString())
            ->willReturn($identity);

        $this->expectException(RuntimeException::class);
        $this->service->assignEmployee($this->subscription, $this->assigneeUuid);
    }

    #[Test]
    public function assignEmployeeSetsAssigneeMetadataForEmployeeIdentity(): void
    {
        $identity = new Identity(
            id: $this->assigneeUuid->toString(),
            schemaId: SchemaId::EMPLOYEE,
            schemaUrl: '',
            state: 'active',
            traits: new IdentityTraits('employee@sandwave.io', null),
            verifiableAddresses: [],
            recoveryAddresses: [],
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
            credentials: null,
            metadataPublic: null,
            metadataAdmin: null,
        );

        $this->lighthouseApiService
            ->expects(self::once())
            ->method('getKratosIdentityByIdentifier')
            ->with($this->assigneeUuid->toString())
            ->willReturn($identity);

        $this->service->assignEmployee($this->subscription, $this->assigneeUuid);

        $assigneeMetadata = $this->subscriptionCategory->refresh()->assignee_metadata;
        self::assertNotNull($assigneeMetadata);
        self::assertSame($identity->id, $assigneeMetadata->uuid->toString());
        self::assertSame('employee@sandwave.io', $assigneeMetadata->email);
    }

    #[Test]
    public function overwriteAsigneeByAsigningDifferentEmployee(): void
    {
        $this->subscriptionCategory->assignee_metadata = new IdentityMetadataDTO(
            Uuid::uuid4(),
            'randomEmployee@sandwave.io',
        );
        $this->subscriptionCategory->save();
        $assigneeMetadata = $this->subscriptionCategory->refresh()->assignee_metadata;
        self::assertNotNull($assigneeMetadata);
        self::assertSame('randomEmployee@sandwave.io', $assigneeMetadata->email);

        $identity = new Identity(
            id: $this->assigneeUuid->toString(),
            schemaId: SchemaId::EMPLOYEE,
            schemaUrl: '',
            state: 'active',
            traits: new IdentityTraits('employee@sandwave.io', null),
            verifiableAddresses: [],
            recoveryAddresses: [],
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
            credentials: null,
            metadataPublic: null,
            metadataAdmin: null,
        );

        $this->lighthouseApiService
            ->expects(self::once())
            ->method('getKratosIdentityByIdentifier')
            ->with($this->assigneeUuid->toString())
            ->willReturn($identity);

        $this->service->assignEmployee($this->subscription, $this->assigneeUuid);

        $assigneeMetadata = $this->subscriptionCategory->refresh()->assignee_metadata;
        self::assertNotNull($assigneeMetadata);
        self::assertSame($identity->id, $assigneeMetadata->uuid->toString());
        self::assertSame('employee@sandwave.io', $assigneeMetadata->email);
    }

    #[Test]
    public function assignCategoryCreatesNewCategoryWhenNoneExists(): void
    {
        $this->subscriptionCategory->delete();
        $this->subscription->unsetRelation('category');

        $this->lighthouseApiService->expects(self::never())->method('getKratosIdentityByIdentifier');

        $this->service->assignCategory($this->subscription, SubscriptionCategory::CUSTOMER_ACTION);

        $category = $this->subscription->refresh()->category;
        self::assertNotNull($category);
        self::assertSame(SubscriptionCategory::CUSTOMER_ACTION, $category->name);
    }

    #[Test]
    public function assignCategoryUpdatesExistingCategory(): void
    {
        self::assertSame(SubscriptionCategory::CUSTOMER_ACTION, $this->subscriptionCategory->refresh()->name);

        $this->lighthouseApiService->expects(self::never())->method('getKratosIdentityByIdentifier');

        $this->service->assignCategory($this->subscription, SubscriptionCategory::TECHNICAL);

        self::assertSame(SubscriptionCategory::TECHNICAL, $this->subscriptionCategory->refresh()->name);
    }

    #[Test]
    public function assignCategoryWithNullClearsCategoryName(): void
    {
        $this->lighthouseApiService->expects(self::never())->method('getKratosIdentityByIdentifier');

        $this->service->assignCategory($this->subscription, null);

        self::assertNull($this->subscriptionCategory->refresh()->name);
    }

    #[Test]
    public function assigneeIsSameAsNewAssignee(): void
    {
        $identity = new Identity(
            id: $this->assigneeUuid->toString(),
            schemaId: SchemaId::EMPLOYEE,
            schemaUrl: '',
            state: 'active',
            traits: new IdentityTraits('employee@sandwave.io', null),
            verifiableAddresses: [],
            recoveryAddresses: [],
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
            credentials: null,
            metadataPublic: null,
            metadataAdmin: null,
        );

        $this->subscriptionCategory->assignee_metadata = new IdentityMetadataDTO(
            Uuid::fromString($identity->id),
            $identity->traits->email,
        );
        $this->subscriptionCategory->save();
        $assigneeMetadata = $this->subscriptionCategory->refresh()->assignee_metadata;
        self::assertNotNull($assigneeMetadata);
        self::assertSame('employee@sandwave.io', $assigneeMetadata->email);

        $this->lighthouseApiService->expects(self::never())->method('getKratosIdentityByIdentifier');

        $this->service->assignEmployee($this->subscription, $this->assigneeUuid);

        $assigneeMetadata = $this->subscriptionCategory->refresh()->assignee_metadata;
        self::assertNotNull($assigneeMetadata);
        self::assertSame($identity->id, $assigneeMetadata->uuid->toString());
        self::assertSame('employee@sandwave.io', $assigneeMetadata->email);
    }
}
