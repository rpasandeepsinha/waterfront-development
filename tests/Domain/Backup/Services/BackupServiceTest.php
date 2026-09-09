<?php

declare(strict_types=1);

namespace Tests\Domain\Backup\Services;

use Exception;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Backup\DTO\BackupUsage;
use Waterfront\Domain\Backup\Services\BackupService;
use Waterfront\Domain\Products\Repositories\BackupProductSpecRepository;
use Waterfront\Domain\Provision\Backup\Requests\CreateBackupRequest;
use Waterfront\Domain\Provision\Backup\Requests\GetBackupSsoRequest;
use Waterfront\Domain\Provision\Backup\Requests\GetBackupUsageRequest;
use Waterfront\Domain\Provision\Backup\Requests\TerminateBackupRequest;
use Waterfront\Domain\Provision\Backup\Results\BackupCreateResult;
use Waterfront\Domain\Provision\Backup\Results\BackupResult;
use Waterfront\Domain\Provision\Backup\Results\BackupUsagesResult;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\Quota;
use Waterfront\Infra\AcronisClient\DTO\Responses\OfferingItems\OfferingItem;
use Waterfront\Infra\AcronisClient\DTO\Tenants\TenantUsage;
use Waterfront\Infra\AcronisClient\DTO\Tenants\TenantUsages;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\MeasurementUnit;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\OfferingItemStatus;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\OfferingItemType;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\UsageName;
use Waterfront\Infra\AcronisClient\Factories\AcronisClientFactory;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Helpers\ByteHelper;

#[CoversClass(BackupService::class)]
#[CoversClass(BackupProductSpecRepository::class)]
class BackupServiceTest extends IntegrationTestCase
{
    private ProvisionGateway&MockInterface $mockProvisionGateway;

    private LoggerInterface&MockInterface $mockLogger;

    private BackupService $backupService;

    public function setUp(): void
    {
        parent::setUp();

        $this->mockProvisionGateway = self::mock(ProvisionGateway::class);
        $this->mockLogger = self::mock(LoggerInterface::class);
        $acronisFactory = self::mock(AcronisClientFactory::class);

        $this->backupService = new BackupService(
            provisionGateway: $this->mockProvisionGateway,
            logger: $this->mockLogger,
            acronisClientFactory: $acronisFactory
        );
    }

    #[Test]
    public function create(): void
    {
        // From backupAcronis state in ProductFactory
        $cloudStorage = 50.0;
        $localStorage = 50.0;
        $mobile = 1;
        $workstation = 2;
        $vms = 3;
        $servers = 4;
        $requestId = 1337;

        $subscription = $this->createBackupSubscription(technicalStatus: TechnicalStatus::PENDING);
        $createRequest = new CreateBackupRequest(
            tagUuid: Uuid::fromString($subscription->uuid),
            email: $subscription->customer->email,
            firstname: $subscription->customer->first_name,
            lastname: $subscription->customer->last_name,
            cloudStorageInGb: $cloudStorage,
            localStorageInGb: $localStorage,
            mobileDevices: $mobile,
            workStations: $workstation,
            servers: $servers,
            vms: $vms,
        );

        $mockSuccessResult = self::mock(BackupCreateResult::class);
        $mockSuccessResult->provisionStatus = ProvisionStatus::SUCCESS;
        $mockSuccessResult->exception = null;
        $mockSuccessResult->validationResult = null;

        $this->mockProvisionGateway
            ->expects('request')
            ->withArgs(
                fn (CreateBackupRequest $request) =>
                $request->cloudStorageInGb === $cloudStorage
                && $request->localStorageInGb === $localStorage
                && $request->mobileDevices === $mobile
                && $request->workStations === $workstation
                && $request->vms === $vms
                && $request->servers === $servers
                && $request->email === $subscription->customer->email
                && $request->firstname === $subscription->customer->first_name
                && $request->lastname === $subscription->customer->last_name
                && $request->tag->toString() === $subscription->uuid
            )
            ->andReturnUsing(function (CreateBackupRequest $request) use ($mockSuccessResult, $requestId) {
                $request->requestId = $requestId;
                $mockSuccessResult->provisionData = $request;
                return $mockSuccessResult;
            });

        $this->mockLogger
            ->expects('debug')
            ->with(
                'Backup provision succeeded',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
                    LoggingContextKeys::META => [
                        'provision_result' => ProvisionStatus::SUCCESS->value,
                        'provision_exception' => null,
                        'provision_request_id' => $requestId,
                        'provision_validation' => null,
                    ],
                ]
            );

        $this->backupService->create($subscription, $createRequest);

        $subscription->refresh();
        self::assertSame(TechnicalStatus::OK->value, $subscription->technical_status);
    }

    #[Test]
    public function createFailed(): void
    {
        // From backupAcronis state in ProductFactory
        $cloudStorage = 50.0;
        $localStorage = 50.0;
        $mobile = 1;
        $workstation = 2;
        $vms = 3;
        $servers = 4;
        $requestId = 1337;
        $exception = new Exception('Provisioning failed');

        $subscription = $this->createBackupSubscription(technicalStatus: TechnicalStatus::PENDING);
        $createRequest = new CreateBackupRequest(
            tagUuid: Uuid::fromString($subscription->uuid),
            email: $subscription->customer->email,
            firstname: $subscription->customer->first_name,
            lastname: $subscription->customer->last_name,
            cloudStorageInGb: $cloudStorage,
            localStorageInGb: $localStorage,
            mobileDevices: $mobile,
            workStations: $workstation,
            servers: $servers,
            vms: $vms,
        );

        $this->mockProvisionGateway
            ->expects('request')
            ->withArgs(
                fn (CreateBackupRequest $request) =>
                $request->cloudStorageInGb === $cloudStorage
                && $request->localStorageInGb === $localStorage
                && $request->mobileDevices === $mobile
                && $request->workStations === $workstation
                && $request->vms === $vms
                && $request->servers === $servers
                && $request->email === $subscription->customer->email
                && $request->firstname === $subscription->customer->first_name
                && $request->lastname === $subscription->customer->last_name
                && $request->tag->toString() === $subscription->uuid
            )
            ->andReturnUsing(function (CreateBackupRequest $request) use ($exception, $requestId) {
                $request->requestId = $requestId;

                return new BackupCreateResult(
                    provisionData: $request,
                    provisionStatus: ProvisionStatus::FAILED,
                    exception: $exception,
                );
            });

        $this->mockLogger
            ->expects('info')
            ->with(
                sprintf('Backup provision failed for subscription uuid %s', $subscription->uuid),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'provision_result' => ProvisionStatus::FAILED->value,
                        'provision_exception' => $exception->getMessage(),
                        'provision_request_id' => $requestId,
                        'provision_validation' => null,
                    ],
                ]
            );

        self::expectException(Exception::class);
        self::expectExceptionMessageIs('Backup provision failed');
        $this->backupService->create($subscription, $createRequest);

        $subscription->refresh();
        self::assertSame(TechnicalStatus::FAILED->value, $subscription->technical_status);
    }

    #[Test]
    public function terminateSuccessMarksDeleted(): void
    {
        $subscription = $this->createBackupSubscription(technicalStatus: TechnicalStatus::OK);
        $requestId = 7331;

        $this->mockProvisionGateway->expects('request')
            ->withArgs(fn (TerminateBackupRequest $request) => $request->tagUuid->toString() === $subscription->uuid)
            ->andReturnUsing(
                function (TerminateBackupRequest $request) use ($requestId) {
                    $request->requestId = $requestId;
                    return new BackupResult(
                        provisionData: $request,
                        provisionStatus: ProvisionStatus::SUCCESS,
                    );
                }
            );

        $this->mockLogger
            ->expects('debug')
            ->with(
                'Backup provision succeeded',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
                    LoggingContextKeys::META => [
                        'provision_result' => ProvisionStatus::SUCCESS->value,
                        'provision_exception' => null,
                        'provision_request_id' => $requestId,
                        'provision_validation' => null,
                    ],
                ]
            );

        $this->backupService->terminate($subscription);
        $subscription->refresh();

        self::assertSame(TechnicalStatus::DELETED->value, $subscription->technical_status);
    }

    #[Test]
    public function terminateFailedMarksDeletingFailed(): void
    {
        $subscription = $this->createBackupSubscription(technicalStatus: TechnicalStatus::OK);
        $requestId = 7331;
        $exception = new Exception('Deletion failed at provision provider');

        $this->mockProvisionGateway->expects('request')
            ->withArgs(fn (TerminateBackupRequest $request) => $request->tagUuid->toString() === $subscription->uuid)
            ->andReturnUsing(
                function (TerminateBackupRequest $request) use ($requestId, $exception) {
                    $request->requestId = $requestId;
                    return new BackupResult(
                        provisionData: $request,
                        provisionStatus: ProvisionStatus::DELETION_FAILED,
                        exception: $exception,
                    );
                }
            );

        $this->mockLogger
            ->expects('info')
            ->with(
                sprintf('Backup termination failed for subscription uuid %s', $subscription->uuid),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
                    LoggingContextKeys::META => [
                        'provision_result' => ProvisionStatus::DELETION_FAILED->value,
                        'provision_exception' => $exception->getMessage(),
                        'provision_request_id' => $requestId,
                        'provision_validation' => null,
                    ],
                ]
            );

        self::expectException(Exception::class);
        self::expectExceptionMessageIs('Backup termination failed');

        $this->backupService->terminate($subscription);

        $subscription->refresh();
        self::assertSame(TechnicalStatus::DELETING_FAILED->value, $subscription->technical_status);
    }

    #[Test]
    public function createReturnsFilledResultWhenResultIsNotExpectedInstance(): void
    {
        $subscription = $this->createBackupSubscription(technicalStatus: TechnicalStatus::PENDING);
        $requestId = 1337;
        $exception = new Exception('Something went wrong');

        $createRequest = new CreateBackupRequest(
            tagUuid: Uuid::fromString($subscription->uuid),
            email: $subscription->customer->email,
            firstname: $subscription->customer->first_name,
            lastname: $subscription->customer->last_name,
        );

        $this->mockProvisionGateway
            ->expects('request')
            ->withArgs(fn (CreateBackupRequest $request) => $request->tag->toString() === $subscription->uuid)
            ->andReturnUsing(function (CreateBackupRequest $request) use ($requestId, $exception) {
                $request->requestId = $requestId;
                return new BackupResult(
                    provisionData: $request,
                    provisionStatus: ProvisionStatus::FAILED,
                    exception: $exception,
                );
            });

        $result = $this->backupService->create($subscription, $createRequest);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($exception, $result->exception);
        self::assertSame($requestId, $result->provisionData->requestId);
        self::assertNull($result->validationResult);
    }

    #[Test]
    public function terminateReturnsFilledResultWhenResultIsNotExpectedInstance(): void
    {
        $subscription = $this->createBackupSubscription(technicalStatus: TechnicalStatus::OK);
        $requestId = 7331;
        $exception = new Exception('Something went wrong');

        $this->mockProvisionGateway
            ->expects('request')
            ->withArgs(fn (TerminateBackupRequest $request) => $request->tagUuid->toString() === $subscription->uuid)
            ->andReturnUsing(function (TerminateBackupRequest $request) use ($requestId, $exception) {
                $request->requestId = $requestId;
                return new BackupCreateResult(
                    provisionData: $request,
                    provisionStatus: ProvisionStatus::FAILED,
                    exception: $exception,
                );
            });

        $result = $this->backupService->terminate($subscription);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($exception, $result->exception);
        self::assertSame($requestId, $result->provisionData->requestId);
        self::assertNull($result->validationResult);
    }

    #[Test]
    public function getSsoUrlReturnsFilledResultWhenResultIsNotExpectedInstance(): void
    {
        $subscription = $this->createBackupSubscription(technicalStatus: TechnicalStatus::OK);
        $requestId = 7331;
        $exception = new Exception('Something went wrong');

        $this->mockProvisionGateway
            ->expects('request')
            ->withArgs(fn (GetBackupSsoRequest $request) => $request->tagUuid->toString() === $subscription->uuid)
            ->andReturnUsing(function (GetBackupSsoRequest $request) use ($requestId, $exception) {
                $request->requestId = $requestId;
                return new BackupResult(
                    provisionData: $request,
                    provisionStatus: ProvisionStatus::FAILED,
                    exception: $exception,
                );
            });

        $result = $this->backupService->getSsoUrl($subscription);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($exception, $result->exception);
        self::assertSame($requestId, $result->provisionData->requestId);
        self::assertNull($result->validationResult);
    }

    #[Test]
    public function getBackupUsagesSuccessReturnsAggregatedUsage(): void
    {
        $subscription = $this->createBackupSubscription(technicalStatus: TechnicalStatus::OK);
        $requestId = 9001;

        $gigabyteInBytes = ByteHelper::BYTES_IN_GIB;

        $tenantUsages = new TenantUsages(items: [
            new TenantUsage(
                applicationId: Uuid::uuid4()->toString(),
                name: 'storage',
                usageName: UsageName::STORAGE,
                type: OfferingItemType::INFRA,
                measurementUnit: MeasurementUnit::BYTES,
                rangeStart: '2017-06-22T00:00:00',
                absoluteValue: 0,
                value: 20 * $gigabyteInBytes,
                infraId: null,
                edition: 'standard',
                offeringItem: new OfferingItem(
                    status: OfferingItemStatus::ACTIVE,
                    quota: new Quota(
                        value: 50 * $gigabyteInBytes,
                        overage: 0,
                        version: 1
                    ),
                ),
            ),

            new TenantUsage(
                applicationId: Uuid::uuid4()->toString(),
                name: 'storage',
                usageName: UsageName::STORAGE,
                type: OfferingItemType::INFRA,
                measurementUnit: MeasurementUnit::BYTES,
                rangeStart: '2017-06-22T00:00:00',
                absoluteValue: 0,
                value: 5 * $gigabyteInBytes,
                infraId: null,
                edition: 'standard',
                offeringItem: new OfferingItem(
                    status: OfferingItemStatus::ACTIVE,
                    quota: new Quota(
                        value: 10 * $gigabyteInBytes,
                        overage: 0,
                        version: 1
                    ),
                ),
            ),

            new TenantUsage(
                applicationId: Uuid::uuid4()->toString(),
                name: 'adv_vms',
                usageName: UsageName::VMS,
                type: OfferingItemType::COUNT,
                measurementUnit: MeasurementUnit::QUANTITY,
                rangeStart: '2017-06-01T00:00:00',
                absoluteValue: 0,
                value: 123,
                infraId: null,
                edition: 'advanced',
            ),
        ]);

        $this->mockProvisionGateway
            ->expects('request')
            ->withArgs(fn (GetBackupUsageRequest $request) => $request->tagUuid->toString() === $subscription->uuid)
            ->andReturnUsing(function (GetBackupUsageRequest $request) use ($requestId, $tenantUsages) {
                $request->requestId = $requestId;
                return new BackupUsagesResult(
                    provisionData: $request,
                    provisionStatus: ProvisionStatus::SUCCESS,
                    tenantUsages: $tenantUsages,
                );
            });

        $usage = $this->backupService->getBackupUsage($subscription);

        self::assertInstanceOf(BackupUsage::class, $usage);
        self::assertSame(25.0, $usage->cloudStorageGbUsed);
        self::assertSame(60.0, $usage->cloudStorageGbTotal);
    }

    #[Test]
    public function getBackupUsagesReturnsNullWhenResultIsNotExpectedInstance(): void
    {
        $subscription = $this->createBackupSubscription(technicalStatus: TechnicalStatus::OK);

        $this->mockProvisionGateway
            ->expects('request')
            ->withArgs(fn (GetBackupUsageRequest $request) => $request->tagUuid->toString() === $subscription->uuid)
            ->andReturnUsing(fn (GetBackupUsageRequest $request) => new BackupResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new Exception('Unexpected result'),
            ));

        self::assertNull($this->backupService->getBackupUsage($subscription));
    }

    #[Test]
    public function getBackupUsagesReturnsNullWhenProvisionStatusIsNotSuccess(): void
    {
        $subscription = $this->createBackupSubscription(technicalStatus: TechnicalStatus::OK);
        $requestId = 9002;

        $this->mockProvisionGateway
            ->expects('request')
            ->withArgs(fn (GetBackupUsageRequest $request) => $request->tagUuid->toString() === $subscription->uuid)
            ->andReturnUsing(function (GetBackupUsageRequest $request) use ($requestId) {
                $request->requestId = $requestId;
                return new BackupUsagesResult(
                    provisionData: $request,
                    provisionStatus: ProvisionStatus::FAILED,
                    exception: new Exception('Failed'),
                );
            });

        self::assertNull($this->backupService->getBackupUsage($subscription));
    }

    #[Test]
    public function getBackupUsagesReturnsNullWhenTenantUsagesIsNull(): void
    {
        $subscription = $this->createBackupSubscription(technicalStatus: TechnicalStatus::OK);
        $requestId = 9003;

        $this->mockProvisionGateway
            ->expects('request')
            ->withArgs(fn (GetBackupUsageRequest $request) => $request->tagUuid->toString() === $subscription->uuid)
            ->andReturnUsing(function (GetBackupUsageRequest $request) use ($requestId) {
                $request->requestId = $requestId;
                return new BackupUsagesResult(
                    provisionData: $request,
                    provisionStatus: ProvisionStatus::SUCCESS,
                );
            });

        self::assertNull($this->backupService->getBackupUsage($subscription));
    }

    #[Test]
    public function getBackupUsagesUnlimitedReturnsNullAvailable(): void
    {
        $subscription = $this->createBackupSubscription(technicalStatus: TechnicalStatus::OK);
        $requestId = 9004;

        $gigabyteInBytes = ByteHelper::BYTES_IN_GIB;

        $tenantUsages = new TenantUsages(items: [
            new TenantUsage(
                applicationId: Uuid::uuid4()->toString(),
                name: 'storage',
                usageName: UsageName::STORAGE,
                type: OfferingItemType::INFRA,
                measurementUnit: MeasurementUnit::BYTES,
                rangeStart: '2017-06-22T00:00:00',
                absoluteValue: 0,
                value: 20 * $gigabyteInBytes,
                infraId: null,
                edition: 'standard',
                offeringItem: new OfferingItem(
                    status: OfferingItemStatus::ACTIVE,
                    quota: new Quota(
                        value: null,
                        overage: null,
                        version: 1
                    ),
                ),
            ),
            new TenantUsage(
                applicationId: Uuid::uuid4()->toString(),
                name: 'storage',
                usageName: UsageName::STORAGE,
                type: OfferingItemType::INFRA,
                measurementUnit: MeasurementUnit::BYTES,
                rangeStart: '2017-06-22T00:00:00',
                absoluteValue: 0,
                value: 5 * $gigabyteInBytes,
                infraId: null,
                edition: 'standard',
                offeringItem: new OfferingItem(
                    status: OfferingItemStatus::ACTIVE,
                    quota: new Quota(
                        value: 10 * $gigabyteInBytes,
                        overage: 0,
                        version: 1
                    ),
                ),
            ),
        ]);

        $this->mockProvisionGateway
            ->expects('request')
            ->withArgs(fn (GetBackupUsageRequest $request) => $request->tagUuid->toString() === $subscription->uuid)
            ->andReturnUsing(function (GetBackupUsageRequest $request) use ($requestId, $tenantUsages) {
                $request->requestId = $requestId;
                return new BackupUsagesResult(
                    provisionData: $request,
                    provisionStatus: ProvisionStatus::SUCCESS,
                    tenantUsages: $tenantUsages,
                );
            });

        $usage = $this->backupService->getBackupUsage($subscription);

        self::assertInstanceOf(BackupUsage::class, $usage);
        self::assertSame(25.0, $usage->cloudStorageGbUsed);
        self::assertNull($usage->cloudStorageGbTotal); // unlimited
    }

    private function createBackupSubscription(
        TechnicalStatus $technicalStatus,
    ): Subscription {
        return SubscriptionFactory::new()
            ->withCustomer()
            ->for(
                ProductFactory::new()->backupAcronis()
            )
            ->technicalStatus($technicalStatus->value)
            ->createOne();
    }
}
