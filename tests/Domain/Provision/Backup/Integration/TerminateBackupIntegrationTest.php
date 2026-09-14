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
use Waterfront\Domain\Provision\Backup\Models\AcronisBackupDeployment;
use Waterfront\Domain\Provision\Backup\Requests\TerminateBackupRequest;
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
use Waterfront\Infra\AcronisClient\Enums\Tenants\ExternalOperationStatus;
use Waterfront\Infra\AcronisClient\Enums\Tenants\MfaStatus;
use Waterfront\Infra\AcronisClient\Enums\Tenants\PricingMode;
use Waterfront\Infra\AcronisClient\Enums\Tenants\TenantType;
use Waterfront\Infra\AcronisClient\Factories\AcronisClientFactory;

#[CoversClass(TerminateBackupRequest::class)]
#[CoversClass(ProvisionGateway::class)]
#[CoversClass(ProvisionTraceabilityService::class)]
#[CoversClass(ProvisioningResultRepository::class)]
#[CoversClass(ProvisioningRequestRepository::class)]
class TerminateBackupIntegrationTest extends IntegrationTestCase
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
    public function terminateBackupRequest(): void
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
        );

        $tenantClient = self::mock(AcronisTenantClient::class);
        $tenantClient->expects('get')->twice()->with($tenantUuid->toString())->andReturn($tenant);

        $tenantClient
            ->expects('update')
            ->once()
            ->withArgs(
                fn (string $receivedTenantUuid, Tenant $tenant) => (
                    $receivedTenantUuid === $tenantUuid->toString()
                    && $tenant->enabled === false
                    && $tenant->version === $tenantVersion
                ),
            )
            ->andReturn($tenant);

        $tenantClient->expects('delete')->once()->with($tenantUuid->toString(), $tenantVersion);

        $acronisClientFactory = self::createMock(AcronisClientFactory::class);
        $acronisClientFactory
            ->expects(self::once())
            ->method('createFromDeployment')
            ->with(self::assertCallbackIsModel($acronisDeployment))
            ->willReturn(new AcronisClient(
                tenantId: Uuid::uuid4(),
                userClient: self::createStub(AcronisUserClient::class),
                offeringItemsClient: self::createStub(AcronisOfferingItemsClient::class),
                tenantClient: $tenantClient,
                genericClient: self::createStub(AcronisGenericClient::class),
            ));

        self::instance(AcronisClientFactory::class, $acronisClientFactory);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $request = new TerminateBackupRequest(
            tagUuid: $tag,
        );

        $this->gateway = self::resolve(ProvisionGateway::class);
        $result = $this->gateway->request($request);

        if ($result->exception !== null) {
            throw $result->exception;
        }

        self::assertInstanceOf(BackupResult::class, $result);
        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);

        $savedRequest = $this->requestRepository->findById($request->requestId);
        self::assertNotNull($savedRequest);

        self::assertSame(ProvisionType::BACKUP, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::TERMINATE_BACKUP, $savedRequest->request_name);

        $savedResult = $this->resultRepository
            ->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedRequest->uuid), 1)
            ->first();

        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::SUCCESS, $savedResult->status);

        self::assertSoftDeleted('backup_deployments_acronis', ['id' => $acronisDeployment->id]);
        self::assertSoftDeleted('backup_deployments', ['id' => $backupDeployment->id]);
    }

    #[Test]
    public function terminateBackupRequestValidationFails(): void
    {
        $tag = Uuid::uuid4();

        $request = new TerminateBackupRequest(
            tagUuid: $tag,
        );

        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::VALIDATION_ERROR, $result->provisionStatus);
        self::assertInstanceOf(ProvisionResult::class, $result);
        self::assertNull($result->exception);
        self::assertNotNull($result->validationResult?->messages);

        self::assertCount(1, $result->validationResult->messages);
        self::assertArrayHasKey('tag', $result->validationResult->messages);
        self::assertSame(
            ['No create request with this tag in the [backup] type.'],
            $result->validationResult->messages['tag'],
        );

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $savedRequest = $this->requestRepository->findById(id: $request->requestId);
        self::assertNotNull($savedRequest);

        self::assertSame(ProvisionType::BACKUP, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::TERMINATE_BACKUP, $savedRequest->request_name);

        $savedResult = $this->resultRepository
            ->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedRequest->uuid), 1)
            ->first();
        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::VALIDATION_ERROR, $savedResult->status);
    }

    #[Test]
    public function terminateBackupRequestFailsExternalOnSuspend(): void
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
        );

        $expectedException = new SaloonException('Suspend failed');

        $tenantClient = self::createMock(AcronisTenantClient::class);

        $tenantClient->expects(self::once())->method('get')->with($tenantUuid->toString())->willReturn($tenant);

        $tenantClient
            ->expects(self::once())
            ->method('update')
            ->with(
                $tenantUuid->toString(),
                self::callback(function ($payload) use ($tenantVersion) {
                    self::assertInstanceOf(Tenant::class, $payload);
                    self::assertFalse($payload->enabled);
                    self::assertSame($tenantVersion, $payload->version);

                    return true;
                }),
            )
            ->willThrowException($expectedException);

        $tenantClient->expects(self::never())->method('delete');

        $acronisClientFactory = self::createMock(AcronisClientFactory::class);
        $acronisClientFactory
            ->expects(self::once())
            ->method('createFromDeployment')
            ->with(self::isInstanceOf(AcronisBackupDeployment::class))
            ->willReturn(new AcronisClient(
                tenantId: Uuid::uuid4(),
                userClient: self::createStub(AcronisUserClient::class),
                offeringItemsClient: self::createStub(AcronisOfferingItemsClient::class),
                tenantClient: $tenantClient,
                genericClient: self::createStub(AcronisGenericClient::class),
            ));

        self::instance(AcronisClientFactory::class, $acronisClientFactory);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $request = new TerminateBackupRequest(tagUuid: $tag);

        $this->gateway = self::resolve(ProvisionGateway::class);
        $result = $this->gateway->request($request);

        self::assertInstanceOf(BackupResult::class, $result);
        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($expectedException, $result->exception);

        $savedRequest = $this->requestRepository->findById($request->requestId);
        self::assertNotNull($savedRequest);

        self::assertSame(ProvisionType::BACKUP, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::TERMINATE_BACKUP, $savedRequest->request_name);

        $savedResult = $this->resultRepository
            ->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedRequest->uuid), 1)
            ->first();

        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::FAILED, $savedResult->status);

        self::assertNotSoftDeleted('backup_deployments_acronis', ['id' => $acronisDeployment->id]);
        self::assertNotSoftDeleted('backup_deployments', ['id' => $backupDeployment->id]);
    }

    #[Test]
    public function terminateBackupRequestFailsExternalOnDelete(): void
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
        );

        $expectedException = new SaloonException('Something went wrong');

        $tenantClient = self::mock(AcronisTenantClient::class);
        $tenantClient->expects('get')->twice()->with($tenantUuid->toString())->andReturn($tenant);

        $tenantClient
            ->expects('update')
            ->once()
            ->withArgs(
                fn (string $receivedTenantUuid, Tenant $tenant) => (
                    $receivedTenantUuid === $tenantUuid->toString()
                    && $tenant->enabled === false
                    && $tenant->version === $tenantVersion
                ),
            )
            ->andReturn($tenant);

        $tenantClient
            ->expects('delete')
            ->once()
            ->with($tenantUuid->toString(), $tenantVersion)
            ->andThrow($expectedException);

        $acronisClientFactory = self::createMock(AcronisClientFactory::class);
        $acronisClientFactory
            ->expects(self::once())
            ->method('createFromDeployment')
            ->with(self::isInstanceOf(AcronisBackupDeployment::class))
            ->willReturn(new AcronisClient(
                tenantId: Uuid::uuid4(),
                userClient: self::createStub(AcronisUserClient::class),
                offeringItemsClient: self::createStub(AcronisOfferingItemsClient::class),
                tenantClient: $tenantClient,
                genericClient: self::createStub(AcronisGenericClient::class),
            ));

        self::instance(AcronisClientFactory::class, $acronisClientFactory);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $request = new TerminateBackupRequest(tagUuid: $tag);

        $this->gateway = self::resolve(ProvisionGateway::class);
        $result = $this->gateway->request($request);

        self::assertInstanceOf(BackupResult::class, $result);
        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($expectedException, $result->exception);

        $savedRequest = $this->requestRepository->findById($request->requestId);
        self::assertNotNull($savedRequest);

        self::assertSame(ProvisionType::BACKUP, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::TERMINATE_BACKUP, $savedRequest->request_name);

        $savedResult = $this->resultRepository
            ->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedRequest->uuid), 1)
            ->first();

        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::FAILED, $savedResult->status);

        self::assertNotSoftDeleted('backup_deployments_acronis', ['id' => $acronisDeployment->id]);
        self::assertNotSoftDeleted('backup_deployments', ['id' => $backupDeployment->id]);
    }

    private function makeTenantDto(string $tenantId, int $version): Tenant
    {
        $contact =
            $this->makeContactDto(
                $tenantId,
            );

        $tenant = new Tenant(
            name: 'Test Tenant',
            parentId: Uuid::uuid4()->toString(),
            kind: TenantType::PARTNER,
            contact: $contact,
            version: $version,
        );

        $tenant->id = $tenantId;
        $tenant->internalTag = Uuid::uuid4()->toString();
        $tenant->customerId = Uuid::uuid4()->toString();
        $tenant->customerType = CustomerType::ENTERPRISE;
        $tenant->contacts = [$contact];
        $tenant->enabled = true;
        $tenant->createdAt = '2020-01-01T00:00:00Z';
        $tenant->updatedAt = '2020-01-01T00:00:00Z';
        $tenant->deletedAt = null;
        $tenant->brandUuid = '770e8400-e29b-41d4-a716-446655440000';
        $tenant->ownerId = '880e8400-e29b-41d4-a716-446655440000';
        $tenant->hasChildren = false;
        $tenant->mfaStatus = MfaStatus::DISABLED;
        $tenant->pricingMode = PricingMode::PRODUCTION;
        $tenant->productionStartDate = '2025-06-01T00:00:00Z';

        $tenant->internalTag = 'internal-tag-123';
        $tenant->language = 'en';
        $tenant->defaultIdpId = '11111111-1111-1111-1111-111111111111';
        $tenant->ancestralAccess = true;
        $tenant->updateLock = null;
        $tenant->settings = null;

        $tenant->brandId = 1;
        $tenant->externalOperationStatus = ExternalOperationStatus::NO_OPERATION;

        return $tenant;
    }

    private function makeContactDto(string $tenantId): Contact
    {
        $contact = new Contact('test@kees.nl');

        $contact->id = Uuid::uuid4()->toString();
        $contact->userId = Uuid::uuid4()->toString();
        $contact->tenantId = $tenantId;
        $contact->types = [ContactType::LEGAL];
        $contact->createdAt = '2020-01-01T00:00:00Z';
        $contact->updatedAt = '2020-01-01T00:00:00Z';
        $contact->id = '550e8400-e29b-41d4-a716-446655440000';
        $contact->address1 = '123 Main Street';
        $contact->address2 = 'Suite 400';
        $contact->country = 'NL';
        $contact->state = 'Noord-Holland';
        $contact->city = 'Amsterdam';
        $contact->zipcode = '1012AB';
        $contact->phone = '+31201234567';
        $contact->firstname = 'John';
        $contact->lastname = 'Doe';
        $contact->title = 'Mr.';
        $contact->website = 'https://example.com';
        $contact->industry = 'Technology';
        $contact->organizationSize = '50-100';
        $contact->emailConfirmed = true;
        $contact->aan = 'AAN123456';
        $contact->language = 'en';
        $contact->fax = '+31207654321';

        return $contact;
    }
}
