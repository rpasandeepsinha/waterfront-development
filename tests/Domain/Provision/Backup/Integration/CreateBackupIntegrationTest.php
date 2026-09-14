<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Backup\Integration;

use Carbon\CarbonImmutable;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Factories\AcronisProviderFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\Backup\Acronis\Enums\Language;
use Waterfront\Domain\Provision\Backup\Exceptions\AcronisClientFactoryException;
use Waterfront\Domain\Provision\Backup\Models\AcronisBackupDeployment;
use Waterfront\Domain\Provision\Backup\Models\BackupDeployment;
use Waterfront\Domain\Provision\Backup\Repositories\AcronisBackupDeploymentRepository;
use Waterfront\Domain\Provision\Backup\Repositories\BackupDeploymentRepository;
use Waterfront\Domain\Provision\Backup\Requests\CreateBackupDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Backup\Requests\CreateBackupRequest;
use Waterfront\Domain\Provision\Backup\Results\BackupCreateResult;
use Waterfront\Domain\Provision\Backup\Services\CreateAcronisProvisionService;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;
use Waterfront\Domain\Provision\Models\ProvisioningResult;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Domain\Provision\Repositories\ProvisioningResultRepository;
use Waterfront\Domain\Provision\Services\ProvisionTraceabilityService;
use Waterfront\Infra\AcronisClient\Clients\AcronisClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisGenericClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisOfferingItemsClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisTenantClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisUserClient;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\OfferingItems;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\TenantUsers;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\User;
use Waterfront\Infra\AcronisClient\DTO\Tenants\Contact;
use Waterfront\Infra\AcronisClient\DTO\Tenants\Tenant;
use Waterfront\Infra\AcronisClient\DTO\Tenants\TenantPricingSettings;
use Waterfront\Infra\AcronisClient\Enums\Tenants\CustomerType;
use Waterfront\Infra\AcronisClient\Enums\Tenants\PricingCurrency;
use Waterfront\Infra\AcronisClient\Enums\Tenants\PricingMode;
use Waterfront\Infra\AcronisClient\Enums\Tenants\TenantType;
use Waterfront\Infra\AcronisClient\Factories\AcronisClientFactory;

#[CoversClass(CreateBackupRequest::class)]
#[CoversClass(ProvisionGateway::class)]
#[CoversClass(ProvisionTraceabilityService::class)]
#[CoversClass(ProvisioningResultRepository::class)]
#[CoversClass(ProvisioningRequestRepository::class)]
#[CoversClass(CreateAcronisProvisionService::class)]
#[CoversClass(BackupDeploymentRepository::class)]
#[CoversClass(AcronisBackupDeploymentRepository::class)]
class CreateBackupIntegrationTest extends IntegrationTestCase
{
    private ProvisionGateway $gateway;

    private ProvisioningRequestRepository $requestRepository;

    private AcronisUserClient&MockInterface $userClient;

    private AcronisTenantClient&MockInterface $tenantClient;

    private AcronisOfferingItemsClient&MockInterface $offeringItemsClient;

    public function setUp(): void
    {
        parent::setUp();

        $this->userClient = self::mock(AcronisUserClient::class);
        $this->tenantClient = self::mock(AcronisTenantClient::class);
        $this->offeringItemsClient = self::mock(AcronisOfferingItemsClient::class);

        $stubAcronisClientFactory = self::createStub(AcronisClientFactory::class);
        $stubAcronisClientFactory
            ->method('getDefault')
            ->willReturn(
                new AcronisClient(
                    tenantId: Uuid::uuid4(),
                    userClient: $this->userClient,
                    offeringItemsClient: $this->offeringItemsClient,
                    tenantClient: $this->tenantClient,
                    genericClient: self::createStub(AcronisGenericClient::class),
                ),
            );

        $this->app->bind(AcronisClientFactory::class, fn () => $stubAcronisClientFactory);

        $this->gateway = self::resolve(ProvisionGateway::class);
        $this->requestRepository = self::resolve(ProvisioningRequestRepository::class);
    }

    #[Test]
    public function createBackupRequestSuccessful(): void
    {
        AcronisProviderFactory::new()->default()->createOne();
        $email = 'test@example.com';
        $firstname = 'John';
        $lastname = 'Doe';
        $username = 'testuser';
        $password = 'Secure_Pass_1234!';
        $language = Language::ENGLISH;

        $request = new CreateBackupRequest(
            tagUuid: $tagUuid = Uuid::uuid4(),
            email: $email,
            firstname: $firstname,
            lastname: $lastname,
            cloudStorageInGb: 100.0,
            localStorageInGb: 100.0,
            username: $username,
            password: $password,
            language: $language,
        );

        $tenant = $this->getTenantDto();
        $this->tenantClient->expects('create')->once()->andReturn($tenant);

        $users = new TenantUsers([]);
        $this->userClient->expects('list')->once()->with($tenant->id)->andReturn($users);

        $user = $this->getUserDto($username);
        $this->userClient->expects('create')->once()->andReturn($user);

        $this->userClient->expects('updatePassword')->once()->with($user->id, self::anything());

        $offeringItems = new OfferingItems(
            checkUsage: false,
            offeringItems: [],
            items: [],
            timestamp: '2026-01-28T00:00:00Z',
        );
        $this->offeringItemsClient->expects('get')->once()->with($tenant->id)->andReturn($offeringItems);

        $this->offeringItemsClient
            ->expects('update')
            ->once()
            ->with($tenant->id, self::anything())
            ->andReturn($offeringItems);

        $this->userClient->expects('updateUserAccessPolicies')->once()->with($user->id, self::anything());

        $receivedPricingSetting = new TenantPricingSettings(
            version: time(),
            mode: PricingMode::TRIAL,
            currency: PricingCurrency::EUR,
        );
        $receivedPricingSetting->productionStartDate = CarbonImmutable::now()->format(DATE_ATOM);

        $this->tenantClient
            ->expects('getPricingSettings')
            ->once()
            ->with($tenant->id)
            ->andReturn($receivedPricingSetting);

        $this->tenantClient
            ->expects('updatePricingSettings')
            ->once()
            ->with(
                $tenant->id,
                self::callback(
                    fn (TenantPricingSettings $settings) => (
                        $settings->mode === PricingMode::PRODUCTION
                        && $settings->currency === $receivedPricingSetting->currency
                        && $settings->version === $receivedPricingSetting->version
                    ),
                ),
            );

        self::assertDatabaseCount(ProvisioningResult::class, 0);
        self::assertDatabaseCount(ProvisioningRequest::class, 0);
        self::assertDatabaseCount(BackupDeployment::class, 0);
        self::assertDatabaseCount(AcronisBackupDeployment::class, 0);

        $result = $this->gateway->request($request);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);
        self::assertDatabaseCount(BackupDeployment::class, 1);
        self::assertDatabaseCount(AcronisBackupDeployment::class, 1);

        self::assertInstanceOf(BackupCreateResult::class, $result);
        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);

        self::assertNull($result->exception);
        self::assertSame($username, $result->username);
        self::assertNotNull($result->password);

        $savedRequest = $this->requestRepository->findById($request->requestId);

        self::assertNotNull($savedRequest);
        self::assertTrue($savedRequest->tag->equals($tagUuid));
        self::assertSame(ProvisionType::BACKUP, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::CREATE_BACKUP, $savedRequest->request_name);
        self::assertStringContainsString($email, $savedRequest->request_data);
        self::assertStringContainsString($firstname, $savedRequest->request_data);
        self::assertStringContainsString($lastname, $savedRequest->request_data);

        $result = $savedRequest->result;

        self::assertNotNull($result);
        self::assertSame(ProvisionStatus::SUCCESS, $result->status);
    }

    #[Test]
    public function createBackupRequestThrowsBackupException(): void
    {
        $request = new CreateBackupRequest(
            tagUuid: $tagUuid = Uuid::uuid4(),
            email: 'test@example.com',
            firstname: 'John',
            lastname: 'Doe',
            cloudStorageInGb: 100.0,
            localStorageInGb: 100.0,
            language: Language::CHINESE_TRADITIONAL,
        );

        $this->tenantClient
            ->expects('create')
            ->once()
            ->andThrow(new AcronisClientFactoryException('Failed to create tenant'));

        $this->userClient->expects('list')->never();

        $this->userClient->expects('create')->never();

        self::assertDatabaseCount(ProvisioningResult::class, 0);
        self::assertDatabaseCount(ProvisioningRequest::class, 0);
        self::assertDatabaseCount(BackupDeployment::class, 0);
        self::assertDatabaseCount(AcronisBackupDeployment::class, 0);

        $result = $this->gateway->request($request);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);
        self::assertDatabaseCount(BackupDeployment::class, 0);
        self::assertDatabaseCount(AcronisBackupDeployment::class, 0);

        self::assertInstanceOf(BackupCreateResult::class, $result);
        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);

        self::assertNotNull($result->exception);
        self::assertInstanceOf(AcronisClientFactoryException::class, $result->exception);

        $savedRequest = $this->requestRepository->findById($request->requestId);

        self::assertNotNull($savedRequest);
        self::assertTrue($savedRequest->tag->equals($tagUuid));
        self::assertSame(ProvisionType::BACKUP, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::CREATE_BACKUP, $savedRequest->request_name);

        $result = $savedRequest->result;

        self::assertNotNull($result);
        self::assertSame(ProvisionStatus::FAILED, $result->status);
    }

    #[Test]
    public function createBackupDeploymentsFromMigrationRequestSuccessful(): void
    {
        $provider = AcronisProviderFactory::new()->default()->createOne();
        $tenantUuid = Uuid::uuid4();
        $userUuid = Uuid::uuid4();
        $tagUuid = Uuid::uuid4();

        $request = new CreateBackupDeploymentsFromMigrationRequest(
            acronisProviderId: $provider->id,
            tenantUuid: $tenantUuid,
            userUuid: $userUuid,
            tag: $tagUuid,
        );

        $this->tenantClient->expects('create')->never();

        $this->userClient->expects('create')->never();

        self::assertDatabaseCount(ProvisioningResult::class, 0);
        self::assertDatabaseCount(ProvisioningRequest::class, 0);
        self::assertDatabaseCount(BackupDeployment::class, 0);
        self::assertDatabaseCount(AcronisBackupDeployment::class, 0);

        $result = $this->gateway->request($request);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);
        self::assertDatabaseCount(BackupDeployment::class, 1);
        self::assertDatabaseCount(AcronisBackupDeployment::class, 1);

        self::assertInstanceOf(BackupCreateResult::class, $result);
        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);

        $acronisBackupDeployment = AcronisBackupDeployment::query()->firstOrFail();

        self::assertSame($provider->id, $acronisBackupDeployment->acronis_provider_id);
        self::assertTrue($acronisBackupDeployment->tenant_uuid->equals($tenantUuid));
        self::assertTrue($acronisBackupDeployment->user_uuid->equals($userUuid));

        $savedRequest = $this->requestRepository->findById($request->requestId);

        self::assertNotNull($savedRequest);
        self::assertTrue($savedRequest->tag->equals($tagUuid));
        self::assertSame(ProvisionType::BACKUP, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::CREATE_BACKUP_DEPLOYMENTS_FROM_MIGRATION, $savedRequest->request_name);

        $savedResult = $savedRequest->result;

        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::SUCCESS, $savedResult->status);

        // Idempotent test, calling gateway with same data again
        $request = new CreateBackupDeploymentsFromMigrationRequest(
            acronisProviderId: $provider->id,
            tenantUuid: $tenantUuid,
            userUuid: $userUuid,
            tag: $tagUuid,
        );

        $result = $this->gateway->request($request);

        self::assertInstanceOf(BackupCreateResult::class, $result);
        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);

        self::assertDatabaseCount(ProvisioningResult::class, 2);
        self::assertDatabaseCount(ProvisioningRequest::class, 2);
        self::assertDatabaseCount(BackupDeployment::class, 1);
        self::assertDatabaseCount(AcronisBackupDeployment::class, 1);
    }

    private function getTenantDto(): Tenant
    {
        $tenant = new Tenant(
            name: '',
            parentId: '',
            kind: TenantType::CUSTOMER,
            contact: new Contact(),
            version: 1,
        );

        $tenant->id = Uuid::uuid4()->toString();
        $tenant->customerType = CustomerType::DEFAULT;
        $tenant->contacts = [];
        $tenant->enabled = true;
        $tenant->createdAt = '2026-01-28T00:00:00Z';
        $tenant->updatedAt = '2026-01-28T00:00:00Z';

        return $tenant;
    }

    private function getUserDto(string $username): User
    {
        return new User(
            id: Uuid::uuid4()->toString(),
            login: $username,
        );
    }
}
