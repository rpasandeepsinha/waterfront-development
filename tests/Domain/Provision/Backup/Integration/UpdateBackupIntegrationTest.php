<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Backup\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Saloon\Exceptions\SaloonException;
use Tests\Factories\AcronisBackupDeploymentFactory;
use Tests\Factories\BackupDeploymentFactory;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\Factories\ProvisioningResultFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\Backup\Models\AcronisBackupDeployment;
use Waterfront\Domain\Provision\Backup\Requests\UpdateBackupRequest;
use Waterfront\Domain\Provision\Backup\Results\BackupUpdateResult;
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
use Waterfront\Infra\AcronisClient\Clients\AcronisClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisGenericClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisOfferingItemsClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisTenantClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisUserClient;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\OfferingItem;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\OfferingItems;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\Quota;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\OfferingItemStatus;
use Waterfront\Infra\AcronisClient\Factories\AcronisClientFactory;

#[CoversClass(UpdateBackupRequest::class)]
class UpdateBackupIntegrationTest extends IntegrationTestCase
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
    public function updateBackupRequestSuccess(): void
    {
        $tag = Uuid::uuid4();
        $tenantUuid = Uuid::uuid4();
        $userUuid = Uuid::uuid4();
        $applicationUuid = Uuid::uuid4();
        $infraUuid = Uuid::uuid4();
        $password = '#$AQ%we5we2!12332112!!!!!ss';
        $version = (int) floor(microtime(true) * 1000);

        AcronisBackupDeploymentFactory::new()->for(
            BackupDeploymentFactory::new()->for(
                ProvisioningRequestFactory::new()
                    ->backup()
                    ->state([
                        'request_name' => ProvisionRequestName::CREATE_BACKUP,
                        'tag' => $tag,
                    ])
                    ->has(ProvisioningResultFactory::new()->success(), 'result'),
                'request',
            ),
        )->createOne([
            'user_uuid' => $userUuid,
            'tenant_uuid' => $tenantUuid,
        ]);

        $userClient = self::createMock(AcronisUserClient::class);
        $userClient
            ->expects(self::once())
            ->method('updatePassword')
            ->with($userUuid->toString(), $password)
            ->willReturn(true);

        $item = new OfferingItem(
            applicationId: $applicationUuid->toString(),
            name: 'pg_base_mobiles',
            tenantId: $tenantUuid->toString(),
            status: OfferingItemStatus::ACTIVE,
            infraId: $infraUuid->toString(),
            quota: new Quota(
                version: $version,
                value: 3,
                overage: null,
            ),
        );
        $offeringItems = new OfferingItems(checkUsage: true, offeringItems: null, items: [$item]);

        $offeringItemsClient = self::createMock(AcronisOfferingItemsClient::class);
        $offeringItemsClient
            ->expects(self::once())
            ->method('get')
            ->with($tenantUuid->toString())
            ->willReturn($offeringItems);

        $offeringItemsClient
            ->expects(self::once())
            ->method('update')
            ->with(
                $tenantUuid->toString(),
                self::callback(function (OfferingItems $payload) {
                    self::assertNotNull($payload->offeringItems);
                    $item = $payload->offeringItems[0];
                    self::assertNotNull($item->quota);

                    self::assertSame(3, $item->quota->value);
                    self::assertNull($item->quota->overage);

                    return true;
                }),
            )
            ->willReturn($offeringItems);

        $acronisClientFactory = self::createMock(AcronisClientFactory::class);
        $acronisClientFactory
            ->expects(self::once())
            ->method('createFromDeployment')
            ->with(self::isInstanceOf(AcronisBackupDeployment::class))
            ->willReturn(new AcronisClient(
                tenantId: $tenantUuid,
                userClient: $userClient,
                offeringItemsClient: $offeringItemsClient,
                tenantClient: self::createStub(AcronisTenantClient::class),
                genericClient: self::createStub(AcronisGenericClient::class),
            ));

        self::instance(AcronisClientFactory::class, $acronisClientFactory);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $request = new UpdateBackupRequest(tagUuid: $tag, password: $password, mobileDevices: 3);

        $this->gateway = self::resolve(ProvisionGateway::class);
        $result = $this->gateway->request($request);

        self::assertInstanceOf(BackupUpdateResult::class, $result);
        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNotNull($result->offeringItems);
        self::assertNotNull($result->offeringItems->items);
        self::assertCount(1, $result->offeringItems->items);
        self::assertNull($result->exception);

        $savedRequest = $this->requestRepository->findById($request->requestId);
        self::assertNotNull($savedRequest);

        self::assertSame(ProvisionType::BACKUP, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::UPDATE_BACKUP, $savedRequest->request_name);
        self::assertSame(
            sprintf(
                '{"vms": null, "servers": null, "password": "****", "websites": null, "m365Seats": null, "m365Teams": null, "workStations": null, "mobileDevices": %d, "hostingServers": null, "cloudStorageInGb": null, "localStorageInGb": null, "m365SharepointSites": null, "googleWorkspaceSeats": null, "enableGoogleWorkspaceDrive": null}',
                3,
            ),
            $savedRequest->request_data,
        );

        $savedResult = $this->resultRepository
            ->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedRequest->uuid), 1)
            ->first();

        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::SUCCESS, $savedResult->status);
    }

    #[Test]
    public function updateBackupRequestValidationFails(): void
    {
        $tag = Uuid::uuid4();

        $request = new UpdateBackupRequest(
            tagUuid: $tag,
        );

        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::VALIDATION_ERROR, $result->provisionStatus);
        self::assertInstanceOf(ProvisionResult::class, $result);
        self::assertNull($result->exception);
        self::assertNotNull($result->validationResult?->messages);

        self::assertCount(2, $result->validationResult->messages);
        self::assertArrayHasKey('tag', $result->validationResult->messages);
        self::assertSame(
            ['At least one resource value must be provided.'],
            $result->validationResult->messages['resources'],
        );
        self::assertSame(
            ['No create request with this tag in the [backup] type.'],
            $result->validationResult->messages['tag'],
        );

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $savedRequest = $this->requestRepository->findById($request->requestId);
        self::assertNotNull($savedRequest);

        self::assertSame(ProvisionType::BACKUP, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::UPDATE_BACKUP, $savedRequest->request_name);
        self::assertSame(
            '{"vms": null, "servers": null, "password": "****", "websites": null, "m365Seats": null, "m365Teams": null, "workStations": null, "mobileDevices": null, "hostingServers": null, "cloudStorageInGb": null, "localStorageInGb": null, "m365SharepointSites": null, "googleWorkspaceSeats": null, "enableGoogleWorkspaceDrive": null}',
            $savedRequest->request_data,
        );

        $savedResult = $this->resultRepository
            ->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedRequest->uuid), 1)
            ->first();

        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::VALIDATION_ERROR, $savedResult->status);
    }

    #[Test]
    public function updateBackupRequestFailsExternal(): void
    {
        $tag = Uuid::uuid4();
        $tenantUuid = Uuid::uuid4();
        $userUuid = Uuid::uuid4();

        AcronisBackupDeploymentFactory::new()->for(
            BackupDeploymentFactory::new()->for(
                ProvisioningRequestFactory::new()
                    ->backup()
                    ->state([
                        'request_name' => ProvisionRequestName::CREATE_BACKUP,
                        'tag' => $tag,
                    ])
                    ->has(ProvisioningResultFactory::new()->success(), 'result'),
                'request',
            ),
        )->createOne([
            'user_uuid' => $userUuid,
            'tenant_uuid' => $tenantUuid,
        ]);

        $expectedException = new SaloonException('Something went wrong');

        $offeringClient = self::createMock(AcronisOfferingItemsClient::class);
        $offeringClient
            ->expects(self::once())
            ->method('get')
            ->with($tenantUuid->toString())
            ->willThrowException($expectedException);

        $acronisClientFactory = self::createMock(AcronisClientFactory::class);
        $acronisClientFactory
            ->expects(self::once())
            ->method('createFromDeployment')
            ->with(self::isInstanceOf(AcronisBackupDeployment::class))
            ->willReturn(new AcronisClient(
                tenantId: $tenantUuid,
                userClient: self::createStub(AcronisUserClient::class),
                offeringItemsClient: $offeringClient,
                tenantClient: self::createStub(AcronisTenantClient::class),
                genericClient: self::createStub(AcronisGenericClient::class),
            ));

        self::instance(AcronisClientFactory::class, $acronisClientFactory);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $request = new UpdateBackupRequest(tagUuid: $tag, servers: 3);

        $this->gateway = self::resolve(ProvisionGateway::class);
        $result = $this->gateway->request($request);

        self::assertInstanceOf(BackupUpdateResult::class, $result);
        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($expectedException, $result->exception);

        $savedRequest = $this->requestRepository->findById($request->requestId);
        self::assertNotNull($savedRequest);

        self::assertSame(ProvisionType::BACKUP, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::UPDATE_BACKUP, $savedRequest->request_name);
        self::assertSame(
            sprintf(
                '{"vms": null, "servers": %d, "password": "****", "websites": null, "m365Seats": null, "m365Teams": null, "workStations": null, "mobileDevices": null, "hostingServers": null, "cloudStorageInGb": null, "localStorageInGb": null, "m365SharepointSites": null, "googleWorkspaceSeats": null, "enableGoogleWorkspaceDrive": null}',
                3,
            ),
            $savedRequest->request_data,
        );

        $savedResult = $this->resultRepository
            ->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedRequest->uuid), 1)
            ->first();

        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::FAILED, $savedResult->status);
    }
}
