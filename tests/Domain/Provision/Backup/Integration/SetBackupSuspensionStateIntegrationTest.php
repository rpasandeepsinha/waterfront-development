<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Backup\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Saloon\Exceptions\SaloonException;
use Tests\Factories\AcronisBackupDeploymentFactory;
use Tests\Factories\AcronisProviderFactory;
use Tests\Factories\BackupDeploymentFactory;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\Factories\ProvisioningResultFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\Backup\Requests\SetBackupSuspensionStateRequest;
use Waterfront\Domain\Provision\Backup\Results\BackupResult;
use Waterfront\Domain\Provision\DTO\ProvisioningResultQueryFilters;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;
use Waterfront\Domain\Provision\Models\ProvisioningResult;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Domain\Provision\Repositories\ProvisioningResultRepository;
use Waterfront\Domain\Provision\Results\ProvisionResult;
use Waterfront\Domain\Provision\Services\ProvisionTraceabilityService;
use Waterfront\Infra\AcronisClient\Clients\AcronisClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisGenericClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisOfferingItemsClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisTenantClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisUserClient;
use Waterfront\Infra\AcronisClient\DTO\Tenants\Contact;
use Waterfront\Infra\AcronisClient\DTO\Tenants\Tenant;
use Waterfront\Infra\AcronisClient\Enums\Tenants\ContactType;
use Waterfront\Infra\AcronisClient\Enums\Tenants\CustomerType;
use Waterfront\Infra\AcronisClient\Enums\Tenants\TenantType;
use Waterfront\Infra\AcronisClient\Factories\AcronisClientFactory;

#[CoversClass(SetBackupSuspensionStateRequest::class)]
#[CoversClass(ProvisionGateway::class)]
#[CoversClass(ProvisionTraceabilityService::class)]
#[CoversClass(ProvisioningResultRepository::class)]
#[CoversClass(ProvisioningRequestRepository::class)]
class SetBackupSuspensionStateIntegrationTest extends IntegrationTestCase
{
    private ProvisionGateway $gateway;

    private ProvisioningResultRepository $resultRepository;

    private ProvisioningRequestRepository $requestRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->gateway = self::resolve(ProvisionGateway::class);
        $this->resultRepository = self::resolve(ProvisioningResultRepository::class);
        $this->requestRepository = self::resolve(ProvisioningRequestRepository::class);
    }

    #[Test]
    public function setBackupSuspensionStateRequestSuccess(): void
    {
        $tag = Uuid::uuid4();
        $originRequestUuid = Uuid::uuid4();
        $tenantUuid = Uuid::uuid4();
        $userUuid = Uuid::uuid4();

        $acronisProvider = AcronisProviderFactory::new()->createOne();

        $originRequest = ProvisioningRequestFactory::new()
            ->backup()
            ->state([
                'request_name' => ProvisionRequestName::CREATE_BACKUP,
                'uuid' => $originRequestUuid,
                'tag' => $tag,
            ])
            ->has(ProvisioningResultFactory::new()->success(), 'result')
            ->createOne();

        $backupDeployment = BackupDeploymentFactory::new()
            ->createOne([
                'origin_provisioning_request_id' => $originRequest->id,
            ]);

        AcronisBackupDeploymentFactory::new()
            ->createOne([
                'backup_deployment_id' => $backupDeployment->id,
                'acronis_provider_id' => $acronisProvider->id,
                'tenant_uuid' => $tenantUuid,
                'user_uuid' => $userUuid,
            ]);

        $tenantVersion = 1559561146223;

        $tenant = $this->makeTenantDto(
            tenantId: $tenantUuid->toString(),
            version: $tenantVersion,
            email: 'test@yourhosting.nl',
            enabled: true,
        );

        $tenantClient = self::createMock(AcronisTenantClient::class);
        $tenantClient
            ->expects(self::once())
            ->method('get')
            ->with($tenantUuid->toString())
            ->willReturn($tenant);

        $tenantClient
            ->expects(self::once())
            ->method('update')
            ->with(
                $tenantUuid->toString(),
                self::callback(function (Tenant $payload) use ($tenantVersion): bool {
                    self::assertSame('Test Tenant', $payload->name);
                    self::assertSame($tenantVersion, $payload->version);

                    self::assertNotNull($payload->contact);
                    self::assertSame('test@yourhosting.nl', $payload->contact->email);

                    self::assertFalse($payload->enabled);

                    return true;
                }),
            );

        $acronisClientFactory = self::createMock(AcronisClientFactory::class);
        $acronisClientFactory
            ->expects(self::once())
            ->method('create')
            ->with(self::assertCallbackIsModel($acronisProvider))
            ->willReturn(new AcronisClient(
                tenantId: $tenantUuid,
                userClient: self::createStub(AcronisUserClient::class),
                offeringItemsClient: self::createStub(AcronisOfferingItemsClient::class),
                tenantClient: $tenantClient,
                genericClient: self::createStub(AcronisGenericClient::class),
            ));

        self::instance(AcronisClientFactory::class, $acronisClientFactory);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $request = new SetBackupSuspensionStateRequest(
            tagUuid: $tag,
            enable: false,
        );

        $this->gateway = self::resolve(ProvisionGateway::class);
        $result = $this->gateway->request($request);

        self::assertInstanceOf(BackupResult::class, $result);
        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);

        $savedRequest = $this->requestRepository->findById($request->requestId);
        self::assertNotNull($savedRequest);

        self::assertSame(ProvisionType::BACKUP, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::SET_BACKUP_SUSPENSION_STATE, $savedRequest->request_name);

        $savedResult = $this->resultRepository
            ->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedRequest->uuid), 1)
            ->first();

        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::SUCCESS, $savedResult->status);
    }

    #[Test]
    public function setBackupSuspensionStateRequestValidationFails(): void
    {
        $tag = Uuid::uuid4();

        $request = new SetBackupSuspensionStateRequest(
            tagUuid: $tag,
            enable: true,
        );

        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::VALIDATION_ERROR, $result->provisionStatus);
        self::assertInstanceOf(ProvisionResult::class, $result);
        self::assertNull($result->exception);
        self::assertNotNull($result->validationResult?->messages);

        self::assertCount(1, $result->validationResult->messages);
        self::assertArrayHasKey('tag', $result->validationResult->messages);
        self::assertSame(['No create request with this tag in the [backup] type.'], $result->validationResult->messages['tag']);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $savedRequest = $this->requestRepository->findById(id: $request->requestId);
        self::assertNotNull($savedRequest);

        self::assertSame(ProvisionType::BACKUP, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::SET_BACKUP_SUSPENSION_STATE, $savedRequest->request_name);

        $savedResult = $this->resultRepository
            ->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedRequest->uuid), 1)
            ->first();
        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::VALIDATION_ERROR, $savedResult->status);
    }

    #[Test]
    public function setBackupSuspensionStateRequestFailsExternalOnGetTenant(): void
    {
        $tag = Uuid::uuid4();
        $originRequestUuid = Uuid::uuid4();
        $tenantUuid = Uuid::uuid4();
        $userUuid = Uuid::uuid4();

        $acronisProvider = AcronisProviderFactory::new()->createOne();

        $originRequest = ProvisioningRequestFactory::new()
            ->backup()
            ->state([
                'request_name' => ProvisionRequestName::CREATE_BACKUP,
                'uuid' => $originRequestUuid,
                'tag' => $tag,
            ])
            ->has(ProvisioningResultFactory::new()->success(), 'result')
            ->createOne();

        $backupDeployment = BackupDeploymentFactory::new()->createOne([
            'origin_provisioning_request_id' => $originRequest->id,
        ]);

        $acronisDeployment = AcronisBackupDeploymentFactory::new()->createOne([
            'backup_deployment_id' => $backupDeployment->id,
            'acronis_provider_id' => $acronisProvider->id,
            'tenant_uuid' => $tenantUuid,
            'user_uuid' => $userUuid,
        ]);

        $expectedException = new SaloonException('Something went wrong');

        $tenantClient = self::createMock(AcronisTenantClient::class);
        $tenantClient
            ->expects(self::once())
            ->method('get')
            ->with($tenantUuid->toString())
            ->willThrowException($expectedException);

        $tenantClient
            ->expects(self::never())
            ->method('update');

        $acronisClientFactory = self::createMock(AcronisClientFactory::class);
        $acronisClientFactory
            ->expects(self::once())
            ->method('create')
            ->with(self::assertCallbackIsModel($acronisProvider))
            ->willReturn(new AcronisClient(
                tenantId: $tenantUuid,
                userClient: self::createStub(AcronisUserClient::class),
                offeringItemsClient: self::createStub(AcronisOfferingItemsClient::class),
                tenantClient: $tenantClient,
                genericClient: self::createStub(AcronisGenericClient::class),
            ));

        self::instance(AcronisClientFactory::class, $acronisClientFactory);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $request = new SetBackupSuspensionStateRequest(
            tagUuid: $tag,
            enable: false,
        );

        $this->gateway = self::resolve(ProvisionGateway::class);
        $result = $this->gateway->request($request);

        self::assertInstanceOf(BackupResult::class, $result);
        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($expectedException, $result->exception);

        $savedRequest = $this->requestRepository->findById($request->requestId);
        self::assertNotNull($savedRequest);

        self::assertSame(ProvisionType::BACKUP, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::SET_BACKUP_SUSPENSION_STATE, $savedRequest->request_name);

        $savedResult = $this->resultRepository
            ->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedRequest->uuid), 1)
            ->first();

        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::FAILED, $savedResult->status);

        self::assertNotSoftDeleted('backup_deployments_acronis', ['id' => $acronisDeployment->id]);
        self::assertNotSoftDeleted('backup_deployments', ['id' => $backupDeployment->id]);
    }

    #[Test]
    public function setBackupSuspensionStateRequestFailsExternalOnUpdateTenant(): void
    {
        $tag = Uuid::uuid4();
        $originRequestUuid = Uuid::uuid4();
        $tenantUuid = Uuid::uuid4();
        $userUuid = Uuid::uuid4();

        $acronisProvider = AcronisProviderFactory::new()->createOne();

        $originRequest = ProvisioningRequestFactory::new()
            ->backup()
            ->state([
                'request_name' => ProvisionRequestName::CREATE_BACKUP,
                'uuid' => $originRequestUuid,
                'tag' => $tag,
            ])
            ->has(ProvisioningResultFactory::new()->success(), 'result')
            ->createOne();

        $backupDeployment = BackupDeploymentFactory::new()->createOne([
            'origin_provisioning_request_id' => $originRequest->id,
        ]);

        $acronisDeployment = AcronisBackupDeploymentFactory::new()->createOne([
            'backup_deployment_id' => $backupDeployment->id,
            'acronis_provider_id' => $acronisProvider->id,
            'tenant_uuid' => $tenantUuid,
            'user_uuid' => $userUuid,
        ]);

        $tenantVersion = 1559561146223;

        $tenant = $this->makeTenantDto(
            tenantId: $tenantUuid->toString(),
            version: $tenantVersion,
            email: 'test@yourhosting.nl',
            enabled: true,
        );

        $expectedException = new SaloonException('Something went wrong');

        $tenantClient = self::createMock(AcronisTenantClient::class);
        $tenantClient
            ->expects(self::once())
            ->method('get')
            ->with($tenantUuid->toString())
            ->willReturn($tenant);

        $tenantClient
            ->expects(self::once())
            ->method('update')
            ->willThrowException($expectedException);

        $acronisClientFactory = self::createMock(AcronisClientFactory::class);
        $acronisClientFactory
            ->expects(self::once())
            ->method('create')
            ->with(self::assertCallbackIsModel($acronisProvider))
            ->willReturn(new AcronisClient(
                tenantId: $tenantUuid,
                userClient: self::createStub(AcronisUserClient::class),
                offeringItemsClient: self::createStub(AcronisOfferingItemsClient::class),
                tenantClient: $tenantClient,
                genericClient: self::createStub(AcronisGenericClient::class),
            ));

        self::instance(AcronisClientFactory::class, $acronisClientFactory);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $request = new SetBackupSuspensionStateRequest(
            tagUuid: $tag,
            enable: false,
        );

        $this->gateway = self::resolve(ProvisionGateway::class);
        $result = $this->gateway->request($request);

        self::assertInstanceOf(BackupResult::class, $result);
        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($expectedException, $result->exception);

        $savedRequest = $this->requestRepository->findById($request->requestId);
        self::assertNotNull($savedRequest);

        self::assertSame(ProvisionType::BACKUP, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::SET_BACKUP_SUSPENSION_STATE, $savedRequest->request_name);

        $savedResult = $this->resultRepository
            ->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedRequest->uuid), 1)
            ->first();

        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::FAILED, $savedResult->status);

        self::assertNotSoftDeleted('backup_deployments_acronis', ['id' => $acronisDeployment->id]);
        self::assertNotSoftDeleted('backup_deployments', ['id' => $backupDeployment->id]);
    }

    private function makeTenantDto(string $tenantId, int $version, string $email, bool $enabled): Tenant
    {
        $contact = new Contact(
            email: $email,
        );

        $contact->id = Uuid::uuid4()->toString();
        $contact->createdAt = '2020-01-01T00:00:00Z';
        $contact->updatedAt = '2020-01-01T00:00:00Z';
        $contact->types = [ContactType::LEGAL];

        $tenant = new Tenant(
            version: $version,
            name: 'Test Tenant',
            parentId: Uuid::uuid4()->toString(),
            kind: TenantType::PARTNER,
            contact: $contact,
        );

        $tenant->id = $tenantId;
        $tenant->customerType = CustomerType::ENTERPRISE;
        $tenant->contacts = [$contact];
        $tenant->enabled = $enabled;
        $tenant->createdAt = '2020-01-01T00:00:00Z';
        $tenant->updatedAt = '2020-01-01T00:00:00Z';

        return $tenant;
    }
}
