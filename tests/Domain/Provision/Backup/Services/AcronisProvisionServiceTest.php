<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Backup\Services;

use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Saloon\Exceptions\SaloonException;
use Tests\Factories\AcronisBackupDeploymentFactory;
use Tests\Factories\AcronisProviderFactory;
use Tests\Factories\BackupDeploymentFactory;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\TestCase;
use Waterfront\Domain\Provision\Backup\Acronis\Helpers\AcronisOfferingItemHelper;
use Waterfront\Domain\Provision\Backup\Acronis\Models\AcronisProvider;
use Waterfront\Domain\Provision\Backup\Exceptions\AcronisSetPasswordException;
use Waterfront\Domain\Provision\Backup\Models\AcronisBackupDeployment;
use Waterfront\Domain\Provision\Backup\Models\BackupDeployment;
use Waterfront\Domain\Provision\Backup\Repositories\BackupDeploymentRepository;
use Waterfront\Domain\Provision\Backup\Requests\CreateBackupRequest;
use Waterfront\Domain\Provision\Backup\Requests\GetBackupSsoRequest;
use Waterfront\Domain\Provision\Backup\Requests\GetBackupUsageRequest;
use Waterfront\Domain\Provision\Backup\Requests\SetBackupSuspensionStateRequest;
use Waterfront\Domain\Provision\Backup\Requests\TerminateBackupRequest;
use Waterfront\Domain\Provision\Backup\Requests\UpdateBackupRequest;
use Waterfront\Domain\Provision\Backup\Services\AcronisProvisionService;
use Waterfront\Domain\Provision\Backup\Services\CreateAcronisProvisionService;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Exceptions\DeploymentNotFoundException;
use Waterfront\Infra\AcronisClient\Clients\AcronisClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisGenericClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisOfferingItemsClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisTenantClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisUserClient;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\OfferingItem;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\OfferingItems;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\Quota;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\OneTimeToken;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\User;
use Waterfront\Infra\AcronisClient\DTO\Tenants\Contact;
use Waterfront\Infra\AcronisClient\DTO\Tenants\Tenant;
use Waterfront\Infra\AcronisClient\DTO\Tenants\TenantUsage;
use Waterfront\Infra\AcronisClient\DTO\Tenants\TenantUsages;
use Waterfront\Infra\AcronisClient\Enums\Infrastructure;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\MeasurementUnit;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\OfferingItemStatus;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\OfferingItemType;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\UsageName;
use Waterfront\Infra\AcronisClient\Enums\Tenants\ContactType;
use Waterfront\Infra\AcronisClient\Enums\Tenants\CustomerType;
use Waterfront\Infra\AcronisClient\Enums\Tenants\ExternalOperationStatus;
use Waterfront\Infra\AcronisClient\Enums\Tenants\MfaStatus;
use Waterfront\Infra\AcronisClient\Enums\Tenants\PricingMode;
use Waterfront\Infra\AcronisClient\Enums\Tenants\TenantType;
use Waterfront\Infra\AcronisClient\Factories\AcronisClientFactory;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Helpers\ByteHelper;

#[CoversClass(AcronisProvisionService::class)]
#[AllowMockObjectsWithoutExpectations]
class AcronisProvisionServiceTest extends TestCase
{
    private const string ENDPOINT = 'https://acronis-endpoint.nl/';
    private const string SSO_ENDPOINT = 'https://acronis-sso-endpoint.nl/';
    private const string TENANT_UUID = '8b9bd08a-5a0c-4f4c-8294-b51b10df47ea';
    private const string USER_UUID = '37b42c20-8770-4e60-8427-ec4a991702a0';
    private const string TAG = '0c2fa5e7-aa8b-40a7-a173-f9cce10487eb';

    public AcronisBackupDeployment $acronisBackupDeployment;

    public BackupDeployment $backupDeployment;

    public BackupDeploymentRepository&MockInterface $mockBackupDeploymentRepository;

    public AcronisUserClient&MockObject $mockAcronisUserClient;

    public AcronisTenantClient&MockInterface $mockAcronisTenantClient;

    public AcronisOfferingItemsClient&MockObject $mockAcronisOfferingItemsClient;

    public AcronisClient $acronisClient;

    public AcronisClientFactory&MockObject $acronisClientFactory;

    public AcronisProvider $acronisProvider;

    public function setUp(): void
    {
        parent::setUp();

        $this->mockAcronisUserClient = self::createMock(AcronisUserClient::class);
        $this->mockAcronisTenantClient = self::mock(AcronisTenantClient::class);
        $this->mockAcronisOfferingItemsClient = self::createMock(AcronisOfferingItemsClient::class);

        $this->acronisClient = new AcronisClient(
            tenantId: Uuid::fromString(self::TENANT_UUID),
            userClient: $this->mockAcronisUserClient,
            offeringItemsClient: $this->mockAcronisOfferingItemsClient,
            tenantClient: $this->mockAcronisTenantClient,
            genericClient: self::createStub(AcronisGenericClient::class),
        );

        BackupDeploymentFactory::dontExpandRelationshipsByDefault();
        AcronisBackupDeploymentFactory::dontExpandRelationshipsByDefault();
        ProvisioningRequestFactory::dontExpandRelationshipsByDefault();
        AcronisProviderFactory::dontExpandRelationshipsByDefault();

        $this->acronisProvider = AcronisProviderFactory::new()->makeOne([
            'endpoint' => self::ENDPOINT,
            'sso_target_url' => self::SSO_ENDPOINT,
        ]);

        $this->backupDeployment = BackupDeploymentFactory::new()->makeOne();
        $this->acronisBackupDeployment = AcronisBackupDeploymentFactory::new()->makeOne([
            'tenant_uuid' => self::TENANT_UUID,
            'user_uuid' => self::USER_UUID,
        ]);

        $this->backupDeployment->setRelation('acronisBackupDeployment', $this->acronisBackupDeployment);
        $this->acronisBackupDeployment->setRelation('backupDeployment', $this->backupDeployment);
        $this->acronisBackupDeployment->setRelation('acronisProvider', $this->acronisProvider);

        $this->mockBackupDeploymentRepository = self::mock(BackupDeploymentRepository::class);

        $this->acronisClientFactory = self::createMock(AcronisClientFactory::class);
    }

    #[Test]
    public function getSsoReturnsUrl(): void
    {
        $ottValue = '0123456789abcdef';

        $this->mockBackupDeploymentRepository
            ->expects('findByTag')
            ->withArgs(fn (UuidInterface $receivedTag) => $receivedTag->toString() === self::TAG)
            ->andReturn($this->backupDeployment);

        $this->acronisClientFactory
            ->expects(self::once())
            ->method('createFromDeployment')
            ->with($this->acronisBackupDeployment)
            ->willReturn($this->acronisClient);

        $this->mockAcronisUserClient
            ->expects(self::once())
            ->method('getSso')
            ->with(self::USER_UUID)
            ->willReturn(new OneTimeToken($ottValue));

        $service = new AcronisProvisionService(
            backupDeploymentRepository: $this->mockBackupDeploymentRepository,
            acronisClientFactory: $this->acronisClientFactory,
            logger: self::createStub(LoggerInterface::class),
            createService: self::createStub(CreateAcronisProvisionService::class),
            acronisOfferingItemHelper: self::createStub(AcronisOfferingItemHelper::class),
        );

        $result = $service->getBackupSso(new GetBackupSsoRequest(tagUuid: Uuid::fromString(self::TAG)));

        $expectedSsoUrl = sprintf(
            '%s/idp/external-login#ott=%s&targetURI=%s',
            self::ENDPOINT,
            rawurlencode($ottValue),
            self::SSO_ENDPOINT
        );

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertSame($expectedSsoUrl, $result->ssoUrl);
        self::assertNull($result->exception);
    }

    #[Test]
    public function getSsoReturnsFailedExternalError(): void
    {
        $expectedException = new SaloonException('Something went wrong');

        $this->mockBackupDeploymentRepository
            ->expects('findByTag')
            ->withArgs(fn (UuidInterface $receivedTag) => $receivedTag->toString() === self::TAG)
            ->andReturn($this->backupDeployment);

        $this->acronisClientFactory
            ->expects(self::once())
            ->method('createFromDeployment')
            ->with($this->acronisBackupDeployment)
            ->willReturn($this->acronisClient);

        $this->mockAcronisUserClient
            ->expects(self::once())
            ->method('getSso')
            ->with(self::USER_UUID)
            ->willThrowException($expectedException);

        $service = new AcronisProvisionService(
            backupDeploymentRepository: $this->mockBackupDeploymentRepository,
            acronisClientFactory: $this->acronisClientFactory,
            logger: self::createStub(LoggerInterface::class),
            createService: self::createStub(CreateAcronisProvisionService::class),
            acronisOfferingItemHelper: self::createStub(AcronisOfferingItemHelper::class),
        );

        $result = $service->getBackupSso(new GetBackupSsoRequest(tagUuid: Uuid::fromString(self::TAG)));

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf($expectedException::class, $result->exception);
        self::assertSame($expectedException->getMessage(), $result->exception->getMessage());
        self::assertNull($result->ssoUrl);
    }

    #[Test]
    public function getSsoDeploymentNotFound(): void
    {
        $this->mockBackupDeploymentRepository = self::mock(BackupDeploymentRepository::class);
        $this->mockBackupDeploymentRepository
            ->expects('findByTag')
            ->withArgs(fn (UuidInterface $receivedTag) => $receivedTag->toString() === self::TAG)
            ->andReturn(null);

        $acronisClientFactory = self::createMock(AcronisClientFactory::class);
        $acronisClientFactory
            ->expects(self::never())
            ->method('createFromDeployment');

        $service = new AcronisProvisionService(
            backupDeploymentRepository: $this->mockBackupDeploymentRepository,
            acronisClientFactory: $acronisClientFactory,
            logger: self::createStub(LoggerInterface::class),
            createService: self::createStub(CreateAcronisProvisionService::class),
            acronisOfferingItemHelper: self::createStub(AcronisOfferingItemHelper::class),
        );

        $result = $service->getBackupSso(new GetBackupSsoRequest(tagUuid: Uuid::fromString(self::TAG)));

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(DeploymentNotFoundException::class, $result->exception);
        self::assertSame(
            sprintf('No backup deployment found for the given tag [%s].', self::TAG),
            $result->exception->getMessage(),
        );
    }

    #[Test]
    public function terminateBackupSuccess(): void
    {
        $tenantVersion = 123;
        $firstTenantFromApi = $this->makeTenantDto(
            tenantId: self::TENANT_UUID,
            version: $tenantVersion,
            email: 'test@yourhosting.nl',
            enabled: true,
        );

        $afterUpdateTenant = $this->makeTenantDto(
            tenantId: self::TENANT_UUID,
            version: $tenantVersion + 1,
            email: 'test@yourhosting.nl',
            enabled: false,
        );

        $this->mockBackupDeploymentRepository
            ->expects('findByTag')
            ->withArgs(fn (UuidInterface $receivedTag) => $receivedTag->toString() === self::TAG)
            ->andReturn($this->backupDeployment);

        $this->mockAcronisTenantClient
            ->expects('get')
            ->twice()
            ->with(self::TENANT_UUID)
            ->andReturnValues(
                [
                    $firstTenantFromApi,
                    $afterUpdateTenant,
                ]
            );

        $this->mockAcronisTenantClient->expects('update')
            ->once()
            ->withArgs(
                fn (string $receivedTenantUuid, Tenant $receivedTenant) =>
                    $receivedTenantUuid === self::TENANT_UUID
                    && $receivedTenant->enabled === false
            )
            ->andReturn($afterUpdateTenant);

        $this->mockAcronisTenantClient
            ->expects('delete')
            ->once()
            ->with(self::TENANT_UUID, $tenantVersion + 1);

        $acronisClientFactory = self::createMock(AcronisClientFactory::class);
        $acronisClientFactory
            ->expects(self::once())
            ->method('createFromDeployment')
            ->willReturn($this->acronisClient);

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $this->mockBackupDeploymentRepository
            ->expects('deleteBackupAndChildren')
            ->with($this->backupDeployment)
            ->once()
            ->andReturnTrue();

        $service = new AcronisProvisionService(
            backupDeploymentRepository: $this->mockBackupDeploymentRepository,
            acronisClientFactory: $acronisClientFactory,
            logger: self::createStub(LoggerInterface::class),
            createService: self::createStub(CreateAcronisProvisionService::class),
            acronisOfferingItemHelper: self::createStub(AcronisOfferingItemHelper::class),
        );

        $result = $service->terminateBackup(new TerminateBackupRequest(tagUuid: Uuid::fromString(self::TAG)));

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    #[Test]
    public function terminateBackupFailsOnDeleteExternalError(): void
    {
        $tenantVersion = 123;
        $expectedException = new SaloonException('Something went wrong');

        $this->mockBackupDeploymentRepository
            ->expects('findByTag')
            ->once()
            ->withArgs(fn (UuidInterface $receivedTag) => $receivedTag->toString() === self::TAG)
            ->andReturn($this->backupDeployment);

        $this->mockBackupDeploymentRepository
            ->expects('deleteBackupAndChildren')
            ->never()
            ->withArgs(fn (UuidInterface $receivedTag) => $receivedTag->toString() === self::TAG)
            ->andReturn($this->backupDeployment);

        $firstTenantFromApi = $this->makeTenantDto(
            tenantId: self::TENANT_UUID,
            version: $tenantVersion,
            email: 'test@yourhosting.nl',
            enabled: true,
        );

        $afterUpdateTenant = $this->makeTenantDto(
            tenantId: self::TENANT_UUID,
            version: $tenantVersion + 1,
            email: 'test@yourhosting.nl',
            enabled: false,
        );

        $this->mockAcronisTenantClient
            ->expects('get')
            ->twice()
            ->with(self::TENANT_UUID)
            ->andReturnValues(
                [
                    $firstTenantFromApi,
                    $afterUpdateTenant,
                ]
            );

        $this->mockAcronisTenantClient->expects('update')
            ->once()
            ->withArgs(
                fn (string $receivedTenantUuid, Tenant $receivedTenant) =>
                $receivedTenantUuid === self::TENANT_UUID
                && $receivedTenant->enabled === false
            )
            ->andReturn($afterUpdateTenant);

        $this->mockAcronisTenantClient
            ->expects('delete')
            ->once()
            ->with(self::TENANT_UUID, $tenantVersion + 1)
            ->andThrow($expectedException);

        $client = new AcronisClient(
            tenantId: Uuid::uuid4(),
            userClient: self::createStub(AcronisUserClient::class),
            offeringItemsClient: self::createStub(AcronisOfferingItemsClient::class),
            tenantClient: $this->mockAcronisTenantClient,
            genericClient: self::createStub(AcronisGenericClient::class),
        );

        $this->acronisClientFactory
            ->expects(self::once())
            ->method('createFromDeployment')
            ->willReturn($client);

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $service = new AcronisProvisionService(
            backupDeploymentRepository: $this->mockBackupDeploymentRepository,
            acronisClientFactory: $this->acronisClientFactory,
            logger: $logger,
            createService: self::createStub(CreateAcronisProvisionService::class),
            acronisOfferingItemHelper: self::createStub(AcronisOfferingItemHelper::class),
        );

        $request = new TerminateBackupRequest(tagUuid: Uuid::fromString(self::TAG));
        $request->requestId = 1234;

        $result = $service->terminateBackup($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($expectedException, $result->exception);
    }

    #[Test]
    public function updateBackupSuccess(): void
    {
        $applicationUuid = Uuid::uuid4();
        $version = (int) floor(microtime(true) * 1000);

        $item = new OfferingItem(
            applicationId: $applicationUuid->toString(),
            name: 'pg_base_mobiles',
            tenantId: self::TENANT_UUID,
            status: OfferingItemStatus::ACTIVE,
            infraId: null,
            quota: new Quota(
                version: $version,
                value: 3,
                overage: 0,
            ),
        );
        $offeringItems = new OfferingItems(checkUsage: true, offeringItems: null, items: [$item]);

        $this->mockBackupDeploymentRepository
            ->expects('findByTag')
            ->once()
            ->withArgs(fn (UuidInterface $receivedTag) => $receivedTag->toString() === self::TAG)
            ->andReturn($this->backupDeployment);

        $this->mockAcronisUserClient
            ->expects(self::never())
            ->method('updatePassword');

        $this->mockAcronisOfferingItemsClient
            ->expects(self::once())
            ->method('get')
            ->with(self::TENANT_UUID)
            ->willReturn($offeringItems);

        $this->mockAcronisOfferingItemsClient
            ->expects(self::once())
            ->method('update')
            ->with(
                self::TENANT_UUID,
                self::callback(function (OfferingItems $payload) {
                    self::assertNotNull($payload->offeringItems);
                    $item = $payload->offeringItems[0];
                    self::assertNotNull($item->quota);

                    self::assertSame(3, $item->quota->value);
                    self::assertSame(0, $item->quota->overage);

                    return true;
                })
            )
            ->willReturn($offeringItems);

        $acronisClientFactory = self::createMock(AcronisClientFactory::class);
        $acronisClientFactory
            ->expects(self::once())
            ->method('createFromDeployment')
            ->willReturn($this->acronisClient);

        $service = new AcronisProvisionService(
            backupDeploymentRepository: $this->mockBackupDeploymentRepository,
            acronisClientFactory: $acronisClientFactory,
            logger: self::createStub(LoggerInterface::class),
            createService: self::createStub(CreateAcronisProvisionService::class),
            acronisOfferingItemHelper: $this->app->make(AcronisOfferingItemHelper::class),
        );

        $result = $service->updateBackup(
            new UpdateBackupRequest(
                tagUuid: Uuid::fromString(self::TAG),
                mobileDevices: 3,
            )
        );

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    #[Test]
    public function updateBackupNoPassword(): void
    {
        $applicationUuid = Uuid::uuid4();
        $mobileDevicesAmount = 3;
        $version = (int) floor(microtime(true) * 1000);

        $this->mockBackupDeploymentRepository
            ->expects('findByTag')
            ->once()
            ->withArgs(fn (UuidInterface $receivedTag) => $receivedTag->toString() === self::TAG)
            ->andReturn($this->backupDeployment);

        $item = new OfferingItem(
            applicationId: $applicationUuid->toString(),
            name: 'pg_base_mobiles',
            tenantId: self::TENANT_UUID,
            status: OfferingItemStatus::ACTIVE,
            infraId: null,
            quota: new Quota(
                version: $version,
                value: 3,
                overage: 0,
            ),
        );
        $offeringItems = new OfferingItems(checkUsage: true, offeringItems: null, items: [$item]);

        $this->mockAcronisUserClient
            ->expects(self::never())
            ->method('updatePassword');

        $this->mockAcronisOfferingItemsClient
            ->expects(self::once())
            ->method('get')
            ->with(self::TENANT_UUID)
            ->willReturn($offeringItems);

        $this->mockAcronisOfferingItemsClient
            ->expects(self::once())
            ->method('update')
            ->with(
                self::TENANT_UUID,
                self::callback(function (OfferingItems $payload) {
                    self::assertNotNull($payload->offeringItems);
                    $item = $payload->offeringItems[0];
                    self::assertNotNull($item->quota);

                    self::assertSame(3, $item->quota->value);
                    self::assertSame(0, $item->quota->overage);

                    return true;
                })
            )
            ->willReturn($offeringItems);

        $this->acronisClientFactory
            ->expects(self::once())
            ->method('createFromDeployment')
            ->willReturn($this->acronisClient);

        $service = new AcronisProvisionService(
            backupDeploymentRepository: $this->mockBackupDeploymentRepository,
            acronisClientFactory: $this->acronisClientFactory,
            logger: self::createStub(LoggerInterface::class),
            createService: self::createStub(CreateAcronisProvisionService::class),
            acronisOfferingItemHelper: $this->app->make(AcronisOfferingItemHelper::class),
        );

        $result = $service->updateBackup(
            new UpdateBackupRequest(
                tagUuid: Uuid::fromString(self::TAG),
                mobileDevices: $mobileDevicesAmount
            )
        );

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    #[Test]
    public function updateBackupOnlyPassword(): void
    {
        $password = '$$$PPPaaasswooord2!!!';

        $this->mockBackupDeploymentRepository
            ->expects('findByTag')
            ->once()
            ->withArgs(fn (UuidInterface $receivedTag) => $receivedTag->toString() === self::TAG)
            ->andReturn($this->backupDeployment);

        $this->mockAcronisUserClient
            ->expects(self::once())
            ->method('updatePassword')
            ->with(self::USER_UUID, $password)
            ->willReturn(true);

        $this->mockAcronisOfferingItemsClient
            ->expects(self::never())
            ->method('get');

        $this->mockAcronisOfferingItemsClient
            ->expects(self::never())
            ->method('update');

        $this->acronisClientFactory
            ->expects(self::once())
            ->method('createFromDeployment')
            ->willReturn($this->acronisClient);

        $service = new AcronisProvisionService(
            backupDeploymentRepository: $this->mockBackupDeploymentRepository,
            acronisClientFactory: $this->acronisClientFactory,
            logger: self::createStub(LoggerInterface::class),
            createService: self::createStub(CreateAcronisProvisionService::class),
            acronisOfferingItemHelper: $this->app->make(AcronisOfferingItemHelper::class),
        );

        $result = $service->updateBackup(
            new UpdateBackupRequest(
                tagUuid: Uuid::fromString(self::TAG),
                password: $password
            )
        );

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
        self::assertNull($result->offeringItems);
    }

    #[Test]
    public function updateBackupFailedExternalError(): void
    {
        $password = 'password';
        $mobileDevicesAmount = 3;

        $expectedException = new SaloonException('Something went wrong');

        $this->mockBackupDeploymentRepository
            ->expects('findByTag')
            ->once()
            ->withArgs(fn (UuidInterface $receivedTag) => $receivedTag->toString() === self::TAG)
            ->andReturn($this->backupDeployment);

        $this->mockAcronisUserClient
            ->expects(self::once())
            ->method('updatePassword')
            ->with(self::USER_UUID, $password)
            ->willThrowException($expectedException);

        $this->acronisClientFactory
            ->expects(self::once())
            ->method('createFromDeployment')
            ->willReturn($this->acronisClient);

        $service = new AcronisProvisionService(
            backupDeploymentRepository: $this->mockBackupDeploymentRepository,
            acronisClientFactory: $this->acronisClientFactory,
            logger: self::createStub(LoggerInterface::class),
            createService: self::createStub(CreateAcronisProvisionService::class),
            acronisOfferingItemHelper: self::createStub(AcronisOfferingItemHelper::class),
        );

        $request = new UpdateBackupRequest(
            tagUuid: Uuid::fromString(self::TAG),
            password: $password,
            mobileDevices: $mobileDevicesAmount
        );

        $request->requestId = 1234;

        $result = $service->updateBackup(
            $request
        );

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($expectedException, $result->exception);
        self::assertNull($result->offeringItems);
    }

    #[Test]
    public function updateBackupDeploymentNotFound(): void
    {
        $this->acronisClientFactory
            ->expects(self::never())
            ->method('createFromDeployment');

        $this->mockBackupDeploymentRepository
            ->expects('findByTag')
            ->once()
            ->withArgs(fn (UuidInterface $receivedTag) => $receivedTag->toString() === self::TAG)
            ->andReturn(null);

        $service = new AcronisProvisionService(
            backupDeploymentRepository: $this->mockBackupDeploymentRepository,
            acronisClientFactory: $this->acronisClientFactory,
            logger: self::createStub(LoggerInterface::class),
            createService: self::createStub(CreateAcronisProvisionService::class),
            acronisOfferingItemHelper: $this->app->make(AcronisOfferingItemHelper::class),
        );

        $result = $service->updateBackup(
            new UpdateBackupRequest(
                tagUuid: Uuid::fromString(self::TAG),
                password: 'password',
                mobileDevices: 3
            )
        );

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(DeploymentNotFoundException::class, $result->exception);
        self::assertSame(
            sprintf('No backup deployment found for the given tag [%s].', self::TAG),
            $result->exception->getMessage(),
        );
    }

    #[Test]
    public function setBackupSuspensionStateSuccess(): void
    {
        $tenantVersion = 123;

        $this->mockBackupDeploymentRepository
            ->expects('findByTag')
            ->once()
            ->withArgs(fn (UuidInterface $receivedTag) => $receivedTag->toString() === self::TAG)
            ->andReturn($this->backupDeployment);

        $this->mockAcronisTenantClient
            ->expects('get')
            ->once()
            ->with(self::TENANT_UUID)
            ->andReturn(
                $this->makeTenantDto(
                    tenantId: self::TENANT_UUID,
                    version: $tenantVersion,
                    email: 'test@yourhosting.nl',
                    enabled: true,
                )
            );

        $this->mockAcronisTenantClient
            ->expects('update')
            ->once()
            ->with(
                self::TENANT_UUID,
                self::callback(function (Tenant $payload): bool {
                    self::assertSame('Test Tenant', $payload->name);
                    self::assertNotNull($payload->contact);
                    self::assertSame('test@yourhosting.nl', $payload->contact->email);
                    self::assertSame(123, $payload->version);
                    self::assertFalse($payload->enabled);

                    return true;
                })
            );

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $this->acronisClientFactory
            ->expects(self::once())
            ->method('create')
            ->willReturn($this->acronisClient);

        $service = new AcronisProvisionService(
            backupDeploymentRepository: $this->mockBackupDeploymentRepository,
            acronisClientFactory: $this->acronisClientFactory,
            logger: $logger,
            createService: self::createStub(CreateAcronisProvisionService::class),
            acronisOfferingItemHelper: $this->app->make(AcronisOfferingItemHelper::class),
        );

        $request = new SetBackupSuspensionStateRequest(tagUuid: Uuid::fromString(self::TAG), enable: false);
        $request->requestId = 1234;

        $result = $service->setBackupSuspensionState(
            $request
        );

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    #[Test]
    public function setBackupSuspensionStateDeploymentNotFound(): void
    {
        $this->mockBackupDeploymentRepository
            ->expects('findByTag')
            ->once()
            ->withArgs(fn (UuidInterface $receivedTag) => $receivedTag->toString() === self::TAG)
            ->andReturn(null);

        $this->acronisClientFactory->expects(self::never())->method('create');

        $service = new AcronisProvisionService(
            backupDeploymentRepository: $this->mockBackupDeploymentRepository,
            acronisClientFactory: $this->acronisClientFactory,
            logger: self::createStub(LoggerInterface::class),
            createService: self::createStub(CreateAcronisProvisionService::class),
            acronisOfferingItemHelper: $this->app->make(AcronisOfferingItemHelper::class),
        );

        $result = $service->setBackupSuspensionState(
            new SetBackupSuspensionStateRequest(tagUuid: Uuid::fromString(self::TAG), enable: true)
        );

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(DeploymentNotFoundException::class, $result->exception);
        self::assertSame(
            sprintf('No backup deployment found for the given tag [%s].', self::TAG),
            $result->exception->getMessage(),
        );
    }

    #[Test]
    public function setBackupSuspensionStateFailsOnGetTenantExternalError(): void
    {
        $this->mockBackupDeploymentRepository
            ->expects('findByTag')
            ->once()
            ->withArgs(fn (UuidInterface $receivedTag) => $receivedTag->toString() === self::TAG)
            ->andReturn($this->backupDeployment);

        $expectedException = new SaloonException('Something went wrong');

        $this->mockAcronisTenantClient
            ->expects('get')
            ->once()
            ->with(self::TENANT_UUID)
            ->andThrow($expectedException);

        $this->mockAcronisTenantClient
            ->expects('update')
            ->never();

        $this->acronisClientFactory
            ->expects(self::once())
            ->method('create')
            ->willReturn($this->acronisClient);

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $service = new AcronisProvisionService(
            backupDeploymentRepository: $this->mockBackupDeploymentRepository,
            acronisClientFactory: $this->acronisClientFactory,
            logger: $logger,
            createService: self::createStub(CreateAcronisProvisionService::class),
            acronisOfferingItemHelper: $this->app->make(AcronisOfferingItemHelper::class),
        );

        $request = new SetBackupSuspensionStateRequest(tagUuid: Uuid::fromString(self::TAG), enable: true);
        $request->requestId = 1234;

        $result = $service->setBackupSuspensionState(
            $request
        );

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($expectedException, $result->exception);
    }

    #[Test]
    public function setBackupSuspensionStateFailsOnUpdateTenantExternalError(): void
    {
        $tenantVersion = 123;

        $this->mockBackupDeploymentRepository
            ->expects('findByTag')
            ->once()
            ->withArgs(fn (UuidInterface $receivedTag) => $receivedTag->toString() === self::TAG)
            ->andReturn($this->backupDeployment);

        $expectedException = new SaloonException('Something went wrong');

        $this->mockAcronisTenantClient
            ->expects('get')
            ->once()
            ->with(self::TENANT_UUID)
            ->andReturn(
                $this->makeTenantDto(
                    tenantId: self::TENANT_UUID,
                    version: $tenantVersion,
                    email: 'test@yourhosting.nl',
                    enabled: true,
                )
            );

        $this->mockAcronisTenantClient
            ->expects('update')
            ->once()
            ->andThrow($expectedException);

        $acronisClientFactory = self::createMock(AcronisClientFactory::class);
        $acronisClientFactory
            ->expects(self::once())
            ->method('create')
            ->willReturn($this->acronisClient);

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $service = new AcronisProvisionService(
            backupDeploymentRepository: $this->mockBackupDeploymentRepository,
            acronisClientFactory: $acronisClientFactory,
            logger: $logger,
            createService: self::createStub(CreateAcronisProvisionService::class),
            acronisOfferingItemHelper: $this->app->make(AcronisOfferingItemHelper::class),
        );

        $request = new SetBackupSuspensionStateRequest(tagUuid: Uuid::fromString(self::TAG), enable: false);
        $request->requestId = 1234;

        $result = $service->setBackupSuspensionState(
            $request
        );

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($expectedException, $result->exception);
    }

    #[Test]
    public function createBackupSuccess(): void
    {
        $tenantId = Uuid::uuid4();
        $userId = Uuid::uuid4();
        $username = 'test-username';
        $password = 'test-password';
        $requestId = 1337;

        $createRequest = new CreateBackupRequest(
            tagUuid: Uuid::fromString(self::TAG),
            email: 'test@email.com',
            firstname: 'Test',
            lastname: 'User',
            cloudStorageInGb: 50,
            localStorageInGb: 50,
            username: $username,
            password: $password,
        );

        $createRequest->requestId = $requestId;

        $tenant = $this->makeTenantDto(
            tenantId: $tenantId->toString(),
            version: time(),
            email: 'test@yourhosting.nl',
            enabled: true,
        );

        $user = new User(
            id: $userId->toString(),
            login: $username,
        );

        $createService = self::createMock(CreateAcronisProvisionService::class);
        $createService
            ->expects(self::once())
            ->method('createTenant')
            ->with($createRequest)
            ->willReturn($tenant);

        $createService
            ->expects(self::once())
            ->method('findOrCreateUser')
            ->with($tenantId, $createRequest)
            ->willReturn($user);

        $createService
            ->expects(self::once())
            ->method('setPassword')
            ->with($userId, $password)
            ->willReturn($password);

        $createService
            ->expects(self::once())
            ->method('updateAccessPolicies')
            ->with($userId, $tenantId, $createRequest);

        $createService
            ->expects(self::once())
            ->method('updatePricingToProduction')
            ->with($tenantId);

        $createService
            ->expects(self::once())
            ->method('storeDeployments')
            ->with($requestId, $tenantId, $userId);

        $acronisClient = new AcronisClient(
            tenantId: Uuid::uuid4(),
            userClient: self::createStub(AcronisUserClient::class),
            offeringItemsClient: $offeringItemClient = self::createMock(AcronisOfferingItemsClient::class),
            tenantClient: self::createStub(AcronisTenantClient::class),
            genericClient: self::createStub(AcronisGenericClient::class),
        );

        $offeringItemClient->expects(self::once())
            ->method('get')
            ->willReturn(
                new OfferingItems(
                    checkUsage: null,
                    offeringItems: null,
                    items: [],
                )
            );

        $clientFactory = self::createMock(AcronisClientFactory::class);
        $clientFactory->expects(self::once())
            ->method('getDefault')
            ->willReturn($acronisClient);

        $service = new AcronisProvisionService(
            backupDeploymentRepository: $this->mockBackupDeploymentRepository,
            acronisClientFactory: $clientFactory,
            logger: self::createStub(LoggerInterface::class),
            createService: $createService,
            acronisOfferingItemHelper: $this->app->make(AcronisOfferingItemHelper::class),
        );

        $result = $service->createBackup($createRequest);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertSame($password, $result->password);
        self::assertSame($username, $result->username);
        self::assertNull($result->exception);
    }

    #[Test]
    public function createBackupReturnPasswordNulAndWarnIfPasswordSetFails(): void
    {
        $tenantId = Uuid::uuid4();
        $userId = Uuid::uuid4();
        $username = 'test-username';
        $password = 'test-password';
        $requestId = 1337;

        $logger = self::mock(LoggerInterface::class);

        $createRequest = new CreateBackupRequest(
            tagUuid: Uuid::fromString(self::TAG),
            email: 'test@email.com',
            firstname: 'Test',
            lastname: 'User',
            cloudStorageInGb: 50,
            localStorageInGb: 50,
            username: $username,
            password: $password,
        );

        $createRequest->requestId = $requestId;
        $createRequest->provider = ProvisionProvider::ACRONIS;

        $tenant = $this->makeTenantDto(
            tenantId: $tenantId->toString(),
            version: time(),
            email: 'test@yourhosting.nl',
            enabled: true,
        );

        $user = new User(
            id: $userId->toString(),
            login: $username,
        );

        $createService = self::createMock(CreateAcronisProvisionService::class);
        $createService
            ->expects(self::once())
            ->method('createTenant')
            ->with($createRequest)
            ->willReturn($tenant);

        $createService
            ->expects(self::once())
            ->method('findOrCreateUser')
            ->with($tenantId, $createRequest)
            ->willReturn($user);

        $passwordException = new AcronisSetPasswordException('error');

        $createService
            ->expects(self::once())
            ->method('setPassword')
            ->with($userId, $password)
            ->willThrowException($passwordException);

        $logger->expects('warning')
            ->with(
                sprintf('Could not set password for user %s', $user->id),
                [
                    LoggingContextKeys::PROVISIONING_REQUEST_ID => self::TAG,
                    LoggingContextKeys::PROVISIONING_TYPE       => ProvisionType::BACKUP,
                    LoggingContextKeys::PROVISIONING_PROVIDER   => ProvisionProvider::ACRONIS,
                    LoggingContextKeys::EXCEPTION               => $passwordException,
                    LoggingContextKeys::META                    => [
                        'tenant_id' => $tenant->id,
                        'user'      => $user->id,
                    ],
                ]
            );

        $createService
            ->expects(self::once())
            ->method('updateAccessPolicies')
            ->with($userId, $tenantId, $createRequest);

        $createService
            ->expects(self::once())
            ->method('updatePricingToProduction')
            ->with($tenantId);

        $createService
            ->expects(self::once())
            ->method('storeDeployments')
            ->with($requestId, $tenantId, $userId);

        $acronisClient = new AcronisClient(
            tenantId: Uuid::uuid4(),
            userClient: self::createStub(AcronisUserClient::class),
            offeringItemsClient: $offeringItemClient = self::createMock(AcronisOfferingItemsClient::class),
            tenantClient: self::createStub(AcronisTenantClient::class),
            genericClient: self::createStub(AcronisGenericClient::class),
        );

        $offeringItemClient->expects(self::once())
            ->method('get')
            ->willReturn(
                new OfferingItems(
                    checkUsage: null,
                    offeringItems: null,
                    items: [],
                )
            );

        $clientFactory = self::createMock(AcronisClientFactory::class);
        $clientFactory->expects(self::once())
            ->method('getDefault')
            ->willReturn($acronisClient);

        $service = new AcronisProvisionService(
            backupDeploymentRepository: $this->mockBackupDeploymentRepository,
            acronisClientFactory: $clientFactory,
            logger: $logger,
            createService: $createService,
            acronisOfferingItemHelper: $this->app->make(AcronisOfferingItemHelper::class),
        );

        $result = $service->createBackup($createRequest);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->password);
        self::assertSame($username, $result->username);
        self::assertNull($result->exception);
    }

    #[Test]
    public function getBackupUsagesSuccess(): void
    {
        $this->mockBackupDeploymentRepository
            ->expects('findByTag')
            ->once()
            ->withArgs(fn (UuidInterface $receivedTag) => $receivedTag->toString() === self::TAG)
            ->andReturn($this->backupDeployment);

        $tenantUsages = new TenantUsages(items: [
            new TenantUsage(
                applicationId: Uuid::uuid4()->toString(),
                name: 'adv_vms',
                usageName: UsageName::VMS,
                type: OfferingItemType::COUNT,
                measurementUnit: MeasurementUnit::QUANTITY,
                rangeStart: '2017-06-01T00:00:00',
                absoluteValue: 800,
                value: 800,
                infraId: null,
                edition: 'advanced',
            ),
        ]);

        $this->mockAcronisTenantClient
            ->expects('getTenantUsages')
            ->once()
            ->with(self::TENANT_UUID, 'storage')
            ->andReturn($tenantUsages);

        $this->acronisClientFactory
            ->expects(self::once())
            ->method('createFromDeployment')
            ->willReturn($this->acronisClient);

        $service = new AcronisProvisionService(
            backupDeploymentRepository: $this->mockBackupDeploymentRepository,
            acronisClientFactory: $this->acronisClientFactory,
            logger: self::createStub(LoggerInterface::class),
            createService: self::createStub(CreateAcronisProvisionService::class),
            acronisOfferingItemHelper: $this->app->make(AcronisOfferingItemHelper::class),
        );

        $result = $service->getBackupUsages(new GetBackupUsageRequest(tagUuid: Uuid::fromString(self::TAG)));

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
        self::assertSame($tenantUsages, $result->tenantUsages);
    }

    #[Test]
    public function getBackupUsagesFailedExternalError(): void
    {
        $this->mockBackupDeploymentRepository
            ->expects('findByTag')
            ->once()
            ->withArgs(fn (UuidInterface $receivedTag) => $receivedTag->toString() === self::TAG)
            ->andReturn($this->backupDeployment);

        $expectedException = new SaloonException('Something went wrong');

        $this->mockAcronisTenantClient
            ->expects('getTenantUsages')
            ->once()
            ->with(self::TENANT_UUID, 'storage')
            ->andThrow($expectedException);

        $this->acronisClientFactory
            ->expects(self::once())
            ->method('createFromDeployment')
            ->willReturn($this->acronisClient);

        $service = new AcronisProvisionService(
            backupDeploymentRepository: $this->mockBackupDeploymentRepository,
            acronisClientFactory: $this->acronisClientFactory,
            logger: self::createStub(LoggerInterface::class),
            createService: self::createStub(CreateAcronisProvisionService::class),
            acronisOfferingItemHelper: $this->app->make(AcronisOfferingItemHelper::class),
        );

        $request = new GetBackupUsageRequest(tagUuid: Uuid::fromString(self::TAG));
        $request->requestId = 1234;

        $result = $service->getBackupUsages($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($expectedException, $result->exception);
        self::assertNull($result->tenantUsages);
    }

    #[Test]
    public function getBackupUsagesDeploymentNotFound(): void
    {
        $this->mockBackupDeploymentRepository
            ->expects('findByTag')
            ->once()
            ->withArgs(fn (UuidInterface $receivedTag) => $receivedTag->toString() === self::TAG)
            ->andReturn(null);

        $this->acronisClientFactory
            ->expects(self::never())
            ->method('createFromDeployment');

        $service = new AcronisProvisionService(
            backupDeploymentRepository: $this->mockBackupDeploymentRepository,
            acronisClientFactory: $this->acronisClientFactory,
            logger: self::createStub(LoggerInterface::class),
            createService: self::createStub(CreateAcronisProvisionService::class),
            acronisOfferingItemHelper: $this->app->make(AcronisOfferingItemHelper::class),
        );

        $result = $service->getBackupUsages(new GetBackupUsageRequest(tagUuid: Uuid::fromString(self::TAG)));

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(DeploymentNotFoundException::class, $result->exception);
        self::assertSame(
            sprintf('No backup deployment found for the given tag [%s].', self::TAG),
            $result->exception->getMessage(),
        );
        self::assertNull($result->tenantUsages);
    }

    #[Test]
    public function updateOfferingItemsUpdatesExistingItemWhenNotAtFirstIndex(): void
    {
        // Acronis returned pg_base_storage at index 0 (with infra_id) and local_storage at index 1
        // array_filter preserves keys, so $item[0] was undefined for local_storage
        $tenantUuid = Uuid::uuid4();
        $applicationUuid = Uuid::uuid4();
        $existingVersion = (int) floor(microtime(true) * 1000);
        $currentStorageBytes = ByteHelper::giBToBytes(100);
        $newStorageGib = 250;
        $newStorageBytes = ByteHelper::giBToBytes($newStorageGib);

        $cloudStorageItem = new OfferingItem(
            applicationId: $applicationUuid->toString(),
            name: 'pg_base_storage',
            tenantId: self::TENANT_UUID,
            status: OfferingItemStatus::ACTIVE,
            infraId: Infrastructure::RECOVERY1->value,
            quota: new Quota(version: $existingVersion, value: $currentStorageBytes, overage: 0),
        );
        $localStorageItem = new OfferingItem(
            applicationId: $applicationUuid->toString(),
            name: 'local_storage',
            tenantId: self::TENANT_UUID,
            status: OfferingItemStatus::ACTIVE,
            infraId: null,
            quota: new Quota(version: $existingVersion, value: $currentStorageBytes, overage: 0),
        );

        $returnedOfferingItems = new OfferingItems(
            checkUsage: true,
            offeringItems: null,
            items: [$cloudStorageItem, $localStorageItem],
        );

        $offeringItemsClient = self::createMock(AcronisOfferingItemsClient::class);
        $offeringItemsClient
            ->expects(self::once())
            ->method('get')
            ->with(self::TENANT_UUID)
            ->willReturn($returnedOfferingItems);

        $offeringItemsClient
            ->expects(self::once())
            ->method('update')
            ->with(
                self::TENANT_UUID,
                self::callback(function (OfferingItems $payload) use ($existingVersion, $newStorageBytes): bool {
                    self::assertNotNull($payload->offeringItems);
                    self::assertCount(2, $payload->offeringItems);

                    [$cloudItem, $localItem] = $payload->offeringItems;

                    self::assertSame('pg_base_storage', $cloudItem->name);
                    self::assertNotNull($cloudItem->quota);
                    self::assertSame($existingVersion, $cloudItem->quota->version);
                    self::assertSame($newStorageBytes, $cloudItem->quota->value);

                    // local_storage was at index 1, so before the array_values() fix $item[0] was null
                    self::assertSame('local_storage', $localItem->name);
                    self::assertNotNull($localItem->quota);
                    self::assertSame($existingVersion, $localItem->quota->version);
                    self::assertSame($newStorageBytes, $localItem->quota->value);

                    return true;
                })
            )
            ->willReturn($returnedOfferingItems);

        $client = new AcronisClient(
            tenantId: $tenantUuid,
            userClient: self::createStub(AcronisUserClient::class),
            offeringItemsClient: $offeringItemsClient,
            tenantClient: self::createStub(AcronisTenantClient::class),
            genericClient: self::createStub(AcronisGenericClient::class),
        );

        $service = new AcronisProvisionService(
            backupDeploymentRepository: $this->mockBackupDeploymentRepository,
            acronisClientFactory: self::createStub(AcronisClientFactory::class),
            logger: self::createStub(LoggerInterface::class),
            createService: self::createStub(CreateAcronisProvisionService::class),
            acronisOfferingItemHelper: $this->app->make(AcronisOfferingItemHelper::class),
        );

        $service->updateOfferingItems(
            provisionData: new UpdateBackupRequest(
                tagUuid: Uuid::uuid4(),
                cloudStorageInGb: $newStorageGib,
                localStorageInGb: $newStorageGib,
            ),
            tenantUuid: self::TENANT_UUID,
            acronisClient: $client,
        );
    }

    private function makeTenantDto(string $tenantId, int $version, string $email, bool $enabled): Tenant
    {
        $contact = $this->makeContactDto(
            $tenantId,
            $email,
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
        $tenant->enabled = $enabled;
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

    private function makeContactDto(string $tenantId, string $email): Contact
    {
        $contact = new Contact($email);

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
