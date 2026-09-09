<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Backup\Services;

use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Tests\TestCase;
use Waterfront\Domain\Provision\Backup\Acronis\Enums\Language;
use Waterfront\Domain\Provision\Backup\Acronis\Repositories\AcronisProviderRepository;
use Waterfront\Domain\Provision\Backup\Models\BackupDeployment;
use Waterfront\Domain\Provision\Backup\Repositories\AcronisBackupDeploymentRepository;
use Waterfront\Domain\Provision\Backup\Repositories\BackupDeploymentRepository;
use Waterfront\Domain\Provision\Backup\Requests\CreateBackupDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Backup\Requests\CreateBackupRequest;
use Waterfront\Domain\Provision\Backup\Services\CreateAcronisProvisionService;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Support\LogContextBuilder;
use Waterfront\Infra\AcronisClient\Clients\AcronisClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisGenericClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisOfferingItemsClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisTenantClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisUserClient;
use Waterfront\Infra\AcronisClient\DTO\Requests\Users\User as UserCreate;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\PolicyItem;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\TenantUsers;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\User;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\UserAccessPolicies;
use Waterfront\Infra\AcronisClient\DTO\Tenants\Tenant;
use Waterfront\Infra\AcronisClient\DTO\Tenants\TenantPricingSettings;
use Waterfront\Infra\AcronisClient\Enums\RoleId;
use Waterfront\Infra\AcronisClient\Enums\Tenants\PricingMode;
use Waterfront\Infra\AcronisClient\Enums\Tenants\TenantType;
use Waterfront\Infra\AcronisClient\Enums\TrusteeType;
use Waterfront\Infra\AcronisClient\Factories\AcronisClientFactory;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(CreateAcronisProvisionService::class)]
class CreateAcronisProvisionServiceTest extends TestCase
{
    private AcronisClientFactory&MockInterface $mockFactory;

    private AcronisUserClient&MockInterface $mockUserClient;

    private AcronisTenantClient&MockInterface $mockTenantClient;

    private AcronisOfferingItemsClient&MockInterface $mockOfferingClient;

    private AcronisBackupDeploymentRepository&MockInterface $mockAcronisRepository;

    private BackupDeploymentRepository&MockInterface $mockBackupRepository;

    private LoggerInterface&MockInterface $mockLogger;

    public function setUp(): void
    {
        parent::setUp();

        $this->mockFactory = self::mock(AcronisClientFactory::class);
        $this->mockUserClient = self::mock(AcronisUserClient::class);
        $this->mockTenantClient = self::mock(AcronisTenantClient::class);
        $this->mockOfferingClient = self::mock(AcronisOfferingItemsClient::class);
        $this->mockLogger = self::mock(LoggerInterface::class);
        $this->mockAcronisRepository = self::mock(AcronisBackupDeploymentRepository::class);
        $this->mockBackupRepository = self::mock(BackupDeploymentRepository::class);
    }

    #[Test]
    public function createDeployment(): void
    {
        $providerId = 1;
        $tenantUuid = Uuid::uuid4();
        $userUuid = Uuid::uuid4();
        $subscriptionUuid = Uuid::uuid4();
        $mockRequestId = 1337;

        $createDeploymentRequest = new CreateBackupDeploymentsFromMigrationRequest(
            $providerId,
            $tenantUuid,
            $userUuid,
            $subscriptionUuid
        );

        $createDeploymentRequest->requestId = $mockRequestId;

        $this->mockLogger
            ->expects('debug')
            ->once()
            ->with(
                'Creating Acronis deployment for Migrations.',
                LogContextBuilder::for($createDeploymentRequest)
                    ->with(LoggingContextKeys::SUBSCRIPTION_UUID, $createDeploymentRequest->tag)
                    ->withMeta([
                        'tenantUuid' => $createDeploymentRequest->tenantUuid,
                        'userUuid' => $createDeploymentRequest->userUuid,
                        'providerId' => $createDeploymentRequest->acronisProviderId,
                    ])
                    ->build()
            );

        $mockBackupDeployment = new BackupDeployment();
        $mockBackupDeployment->id = 100;

        $this->mockBackupRepository
            ->expects('findOrCreate')
            ->with($subscriptionUuid, $mockRequestId)
            ->andReturn($mockBackupDeployment);

        $this->mockAcronisRepository
            ->expects('findOrCreate')
            ->with(
                $mockBackupDeployment->id,
                $providerId,
                $tenantUuid,
                $userUuid,
            );

        $createService = new CreateAcronisProvisionService(
            acronisClientFactory: self::createStub(AcronisClientFactory::class),
            logger: $this->mockLogger,
            backupRepository: $this->mockBackupRepository,
            acronisRepository: $this->mockAcronisRepository,
            acronisProviderRepository: self::createStub(AcronisProviderRepository::class)
        );

        $result = $createService->createDeployment($createDeploymentRequest);

        self::assertSame($createDeploymentRequest, $result->provisionData);
        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
    }

    #[Test]
    public function createTenant(): void
    {
        $expectedFirstname = 'test';
        $expectedLastname = 'kees';
        $expectedEmail = 'test.kees@email.com';
        $expectedTag = Uuid::uuid4();
        $expectedTenantName = sprintf('%s [%s]', $expectedEmail, $expectedTag->toString());
        $expectedTenant = self::createStub(Tenant::class);
        $expectedClientTenantId = Uuid::uuid4();
        $expectedLanguage = Language::DUTCH;

        $request = $this->mockRequest(
            tag: $expectedTag,
            firstname: $expectedFirstname,
            lastname: $expectedLastname,
            email: $expectedEmail,
            language: $expectedLanguage
        );

        $this->mockFactory
            ->expects('getDefault')
            ->once()
            ->andReturn(new AcronisClient(
                tenantId: $expectedClientTenantId,
                userClient: $this->mockUserClient,
                offeringItemsClient: $this->mockOfferingClient,
                tenantClient: $this->mockTenantClient,
                genericClient: self::createStub(AcronisGenericClient::class),
            ));

        $this->mockTenantClient
            ->expects('create')
            ->once()
            ->withArgs(
                fn (Tenant $tenant) =>
                $tenant->name === $expectedTenantName
                && $tenant->parentId === $expectedClientTenantId->toString()
                && $tenant->kind === TenantType::CUSTOMER
                && $tenant->language === $expectedLanguage->value
                && $tenant->contact?->email === $expectedEmail
                && $tenant->contact->firstname === $expectedFirstname
                && $tenant->contact->lastname === $expectedLastname
                && $tenant->contact->language === $expectedLanguage->value
                && $tenant->internalTag === $expectedTag->toString()
                && $tenant->customerId === $expectedEmail
            )
            ->andReturn($expectedTenant);

        $this->mockLogger
            ->expects('info')
            ->once()
            ->with(
                'Creating Acronis tenant.',
                [
                    LoggingContextKeys::PROVISIONING_REQUEST_ID => $expectedTag,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::ACRONIS,
                    LoggingContextKeys::META => [
                        'client_tenant_id' => $expectedClientTenantId,
                    ],
                ]
            );

        $createService = new CreateAcronisProvisionService(
            acronisClientFactory: $this->mockFactory,
            logger: $this->mockLogger,
            backupRepository: self::createStub(BackupDeploymentRepository::class),
            acronisRepository: self::createStub(AcronisBackupDeploymentRepository::class),
            acronisProviderRepository: self::createStub(AcronisProviderRepository::class)
        );

        $tenant = $createService->createTenant($request);

        self::assertSame($expectedTenant, $tenant);
    }

    #[Test]
    public function useExistingUserWhenFound(): void
    {
        $expectedFirstname = 'test';
        $expectedLastname = 'kees';
        $expectedEmail = 'test.kees@email.com';
        $expectedTag = Uuid::uuid4();
        $expectedUser = self::createStub(User::class);
        $expectedTenantId = 'tenant-id';
        $expectedUser1 = 'user1';
        $expectedUser2 = 'user2';
        $expectedClientTenantId = Uuid::uuid4();

        $request = $this->mockRequest(
            tag: $expectedTag,
            firstname: $expectedFirstname,
            lastname: $expectedLastname,
            email: $expectedEmail
        );

        $this->mockFactory
            ->expects('getDefault')
            ->once()
            ->andReturn(new AcronisClient(
                tenantId: $expectedClientTenantId,
                userClient: $this->mockUserClient,
                offeringItemsClient: $this->mockOfferingClient,
                tenantClient: $this->mockTenantClient,
                genericClient: self::createStub(AcronisGenericClient::class),
            ));

        $this->mockUserClient
            ->expects('list')
            ->with($expectedTenantId)
            ->once()
            ->andReturn(new TenantUsers([$expectedUser1, $expectedUser2]));

        $this->mockUserClient
            ->expects('create')
            ->never();

        $this->mockLogger
            ->expects('info')
            ->with(
                'Found more than 1 user for an Acronis tenant. This should not be the case. Using the 1st user.',
                [
                    LoggingContextKeys::PROVISIONING_REQUEST_ID => $expectedTag,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::ACRONIS,
                    LoggingContextKeys::META => [
                        'tenant_id' => $expectedTenantId,
                        'users' => [$expectedUser1, $expectedUser2],
                    ],
                ]
            )
            ->once();

        $this->mockLogger
            ->expects('info')
            ->with('Fetching existing Acronis user for tenant.', [
                LoggingContextKeys::PROVISIONING_REQUEST_ID => $expectedTag,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::ACRONIS,
                LoggingContextKeys::META => [
                    'tenant_id' => $expectedTenantId,
                    'user_id' => $expectedUser1,
                    'client_tenant_id' => $expectedClientTenantId,
                ],
            ]);

        $this->mockUserClient
            ->expects('get')
            ->with($expectedUser1) // When we have multiple users assert the first one is used
            ->once()
            ->andReturn($expectedUser);

        $createService = new CreateAcronisProvisionService(
            acronisClientFactory: $this->mockFactory,
            logger: $this->mockLogger,
            backupRepository: self::createStub(BackupDeploymentRepository::class),
            acronisRepository: self::createStub(AcronisBackupDeploymentRepository::class),
            acronisProviderRepository: self::createStub(AcronisProviderRepository::class)
        );

        $user = $createService->findOrCreateUser($expectedTenantId, $request);

        self::assertSame($expectedUser, $user);
    }

    #[Test]
    public function createUserWhenNotFound(): void
    {
        $expectedFirstname = 'test';
        $expectedLastname = 'kees';
        $expectedEmail = 'test.kees@email.com';
        $expectedTag = Uuid::uuid4();
        $expectedUser = self::createStub(User::class);
        $expectedTenantId = 'tenant-id';
        $expectedLanguage = Language::GERMAN;
        $expectedClientTenantId = Uuid::uuid4();

        $request = $this->mockRequest(
            tag: $expectedTag,
            firstname: $expectedFirstname,
            lastname: $expectedLastname,
            email: $expectedEmail,
            language: $expectedLanguage
        );

        $this->mockFactory
            ->expects('getDefault')
            ->once()
            ->andReturn(new AcronisClient(
                tenantId: $expectedClientTenantId,
                userClient: $this->mockUserClient,
                offeringItemsClient: $this->mockOfferingClient,
                tenantClient: $this->mockTenantClient,
                genericClient: self::createStub(AcronisGenericClient::class),
            ));

        $this->mockUserClient
            ->expects('list')
            ->with($expectedTenantId)
            ->once()
            ->andReturn(new TenantUsers([]));

        $this->mockUserClient
            ->expects('create')
            ->never();

        $this->mockLogger
            ->expects('info')
            ->with(
                'Creating Acronis user for tenant.',
                [
                    LoggingContextKeys::PROVISIONING_REQUEST_ID => $expectedTag,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::ACRONIS,
                    LoggingContextKeys::META => [
                        'tenant_id' => $expectedTenantId,
                        'client_tenant_id' => $expectedClientTenantId,
                    ],
                ]
            );

        $this->mockUserClient
            ->expects('create')
            ->withArgs(
                fn (UserCreate $userCreate) =>
                $userCreate->tenantId === $expectedTenantId
                && strlen($userCreate->login) === 8
                && $userCreate->enabled
                && $userCreate->contact->firstname === $expectedFirstname
                && $userCreate->contact->lastname === $expectedLastname
                && $userCreate->language === $expectedLanguage->value
                && $userCreate->contact->email === $expectedEmail
                && $userCreate->contact->emailConfirmed === true
            )
            ->once()
            ->andReturn($expectedUser);

        $createService = new CreateAcronisProvisionService(
            acronisClientFactory: $this->mockFactory,
            logger: $this->mockLogger,
            backupRepository: self::createStub(BackupDeploymentRepository::class),
            acronisRepository: self::createStub(AcronisBackupDeploymentRepository::class),
            acronisProviderRepository: self::createStub(AcronisProviderRepository::class)
        );

        $user = $createService->findOrCreateUser($expectedTenantId, $request);

        self::assertSame($expectedUser, $user);
    }

    #[Test]
    public function setPasswordWithProvidedPassword(): void
    {
        $expectedUserId = 'user-123';
        $expectedPassword = 'MySecurePassword123';
        $expectedClientTenantId = Uuid::uuid4();

        $this->mockFactory
            ->expects('getDefault')
            ->once()
            ->andReturn(new AcronisClient(
                tenantId: $expectedClientTenantId,
                userClient: $this->mockUserClient,
                offeringItemsClient: $this->mockOfferingClient,
                tenantClient: $this->mockTenantClient,
                genericClient: self::createStub(AcronisGenericClient::class),
            ));

        $this->mockUserClient
            ->expects('updatePassword')
            ->with($expectedUserId, $expectedPassword)
            ->once();

        $createService = new CreateAcronisProvisionService(
            acronisClientFactory: $this->mockFactory,
            logger: $this->mockLogger,
            backupRepository: self::createStub(BackupDeploymentRepository::class),
            acronisRepository: self::createStub(AcronisBackupDeploymentRepository::class),
            acronisProviderRepository: self::createStub(AcronisProviderRepository::class)
        );

        $password = $createService->setPassword($expectedUserId, $expectedPassword);

        self::assertSame($expectedPassword, $password);
    }

    #[Test]
    public function setPasswordWithGeneratedPassword(): void
    {
        $expectedUserId = 'user-123';
        $expectedClientTenantId = Uuid::uuid4();

        $this->mockFactory
            ->expects('getDefault')
            ->once()
            ->andReturn(new AcronisClient(
                tenantId: $expectedClientTenantId,
                userClient: $this->mockUserClient,
                offeringItemsClient: $this->mockOfferingClient,
                tenantClient: $this->mockTenantClient,
                genericClient: self::createStub(AcronisGenericClient::class),
            ));

        $this->mockUserClient
            ->expects('updatePassword')
            ->withArgs(fn (string $userId, string $password) => $userId === $expectedUserId && strlen($password) === 16)
            ->once();

        $createService = new CreateAcronisProvisionService(
            acronisClientFactory: $this->mockFactory,
            logger: $this->mockLogger,
            backupRepository: self::createStub(BackupDeploymentRepository::class),
            acronisRepository: self::createStub(AcronisBackupDeploymentRepository::class),
            acronisProviderRepository: self::createStub(AcronisProviderRepository::class)
        );

        $password = $createService->setPassword($expectedUserId, null);

        self::assertSame(16, strlen($password));
    }

    #[Test]
    public function updateAccessPoliciesLinksUserToTenantAsCompanyAdmin(): void
    {
        $expectedUserId = 'user-456';
        $expectedTenantId = 'tenant-789';
        $expectedTag = Uuid::uuid4();
        $expectedClientTenantId = Uuid::uuid4();

        $request = $this->mockRequest(
            tag: $expectedTag
        );

        $expectedTimestamp = date('c');
        $returnedPolicies = new UserAccessPolicies(
            items: [
                new PolicyItem(
                    tenantId: $expectedTenantId,
                    trusteeId: $expectedUserId,
                    trusteeType: TrusteeType::USER,
                    roleId: RoleId::COMPANY_ADMIN,
                    version: time(),
                ),
                new PolicyItem(
                    tenantId: $expectedTenantId,
                    trusteeId: $expectedUserId,
                    trusteeType: TrusteeType::USER,
                    roleId: RoleId::PROTECTION_ADMIN,
                    version: time(),
                ),
            ],
            timestamp: $expectedTimestamp,
        );

        $this->mockFactory
            ->expects('getDefault')
            ->once()
            ->andReturn(new AcronisClient(
                tenantId: $expectedClientTenantId,
                userClient: $this->mockUserClient,
                offeringItemsClient: $this->mockOfferingClient,
                tenantClient: $this->mockTenantClient,
                genericClient: self::createStub(AcronisGenericClient::class),
            ));

        $this->mockUserClient
            ->expects('updateUserAccessPolicies')
            ->withArgs(function (string $userId, UserAccessPolicies $policies) use ($expectedUserId, $expectedTenantId) {
                self::assertSame($expectedUserId, $userId);
                self::assertCount(2, $policies->items);

                $policyItem = $policies->items[0];
                self::assertSame($expectedTenantId, $policyItem->tenantId);
                self::assertSame($expectedUserId, $policyItem->trusteeId);
                self::assertSame(TrusteeType::USER, $policyItem->trusteeType);
                self::assertSame(RoleId::BACKUP_USER, $policyItem->roleId);
                self::assertIsInt($policyItem->version);
                self::assertGreaterThan(0, $policyItem->version);

                return true;
            })
            ->once()
            ->andReturn($returnedPolicies);

        $this->mockLogger
            ->expects('info')
            ->with(
                sprintf('Updating access policies for user [%s] in tenant [%s].', $expectedUserId, $expectedTenantId),
                [
                    LoggingContextKeys::PROVISIONING_REQUEST_ID => $expectedTag,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::ACRONIS,
                    LoggingContextKeys::META => [
                        'user_id' => $expectedUserId,
                        'tenant_id' => $expectedTenantId,
                        'client_tenant_id' => $expectedClientTenantId,
                    ],
                ]
            )
            ->once();

        $createService = new CreateAcronisProvisionService(
            acronisClientFactory: $this->mockFactory,
            logger: $this->mockLogger,
            backupRepository: self::createStub(BackupDeploymentRepository::class),
            acronisRepository: self::createStub(AcronisBackupDeploymentRepository::class),
            acronisProviderRepository: self::createStub(AcronisProviderRepository::class)
        );

        $result = $createService->updateAccessPolicies($expectedUserId, $expectedTenantId, $request);

        self::assertSame($returnedPolicies, $result);
        self::assertCount(2, $result->items);
        self::assertSame($expectedTenantId, $result->items[0]->tenantId);
        self::assertSame($expectedUserId, $result->items[0]->trusteeId);
        self::assertSame($expectedTenantId, $result->items[1]->tenantId);
        self::assertSame($expectedUserId, $result->items[1]->trusteeId);
    }

    #[Test]
    public function updatePricingToProduction(): void
    {
        $expectedTenantId = 'tenant-id-test';
        $expectedClientTenantId = Uuid::uuid4();

        $this->mockFactory
            ->expects('getDefault')
            ->once()
            ->andReturn(new AcronisClient(
                tenantId: $expectedClientTenantId,
                userClient: $this->mockUserClient,
                offeringItemsClient: $this->mockOfferingClient,
                tenantClient: $this->mockTenantClient,
                genericClient: self::createStub(AcronisGenericClient::class),
            ));

        $this->mockTenantClient
            ->expects('getPricingSettings')
            ->withArgs(
                fn (string $tenantId) =>
                    $tenantId === $expectedTenantId
            );

        $this->mockTenantClient
            ->expects('updatePricingSettings')
            ->withArgs(
                fn (string $tenantId, TenantPricingSettings $payload) =>
                    $tenantId === $expectedTenantId
                    && $payload->mode === PricingMode::PRODUCTION
            );

        $this->mockLogger
            ->expects('info')
            ->with(
                sprintf('Updating pricing to production for tenant %s.', $expectedTenantId),
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::ACRONIS,
                    LoggingContextKeys::META => [
                        'tenant_id' => $expectedTenantId,
                        'client_tenant_id' => $expectedClientTenantId,
                    ],
                ]
            )
            ->once();

        $createService = new CreateAcronisProvisionService(
            acronisClientFactory: $this->mockFactory,
            logger: $this->mockLogger,
            backupRepository: self::createStub(BackupDeploymentRepository::class),
            acronisRepository: self::createStub(AcronisBackupDeploymentRepository::class),
            acronisProviderRepository: self::createStub(AcronisProviderRepository::class)
        );

        $createService->updatePricingToProduction($expectedTenantId);
    }

    private function mockRequest(
        UuidInterface $tag,
        string $firstname = 'test',
        string $lastname = 'kees',
        string $email = 'test.kees@email.nl',
        float $storageInGb = 10.0,
        float $localStorageInGb = 10.0,
        Language $language = Language::ENGLISH,
    ): CreateBackupRequest {
        $request = new CreateBackupRequest(
            tagUuid: $tag,
            email: $email,
            firstname: $firstname,
            lastname: $lastname,
            cloudStorageInGb: $storageInGb,
            localStorageInGb: $localStorageInGb,
            language: $language
        );
        $request->provider = ProvisionProvider::ACRONIS;

        return $request;
    }
}
