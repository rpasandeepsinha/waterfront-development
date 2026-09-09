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
use Waterfront\Domain\Provision\Backup\Requests\GetBackupSsoRequest;
use Waterfront\Domain\Provision\Backup\Results\BackupSsoResult;
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
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\OneTimeToken;
use Waterfront\Infra\AcronisClient\Factories\AcronisClientFactory;

#[CoversClass(GetBackupSsoRequest::class)]
class GetSsoIntegrationTest extends IntegrationTestCase
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
    public function getSsoRequestSuccess(): void
    {
        $tag = Uuid::uuid4();
        $responseOtt = '0123456789abcdef';

        $acronisBackupDeployment = AcronisBackupDeploymentFactory::new()
            ->for(
                BackupDeploymentFactory::new()
                    ->for(
                        ProvisioningRequestFactory::new()
                            ->backup()
                            ->state([
                                'request_name' => ProvisionRequestName::CREATE_BACKUP,
                                'tag' => $tag,
                            ])
                            ->has(ProvisioningResultFactory::new()->success(), 'result'),
                        'request'
                    )
            )
            ->createOne();

        $userClient = self::createMock(AcronisUserClient::class);
        $userClient
            ->expects(self::once())
            ->method('getSso')
            ->with($acronisBackupDeployment->user_uuid->toString())
            ->willReturn(new OneTimeToken($responseOtt));

        $acronisClientFactory = self::createMock(AcronisClientFactory::class);
        $acronisClientFactory
            ->expects(self::once())
            ->method('createFromDeployment')
            ->with(self::isInstanceOf(AcronisBackupDeployment::class))
            ->willReturn(new AcronisClient(
                tenantId: Uuid::uuid4(),
                userClient: $userClient,
                offeringItemsClient: self::createStub(AcronisOfferingItemsClient::class),
                tenantClient: self::createStub(AcronisTenantClient::class),
                genericClient: self::createStub(AcronisGenericClient::class),
            ));

        self::instance(AcronisClientFactory::class, $acronisClientFactory);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $request = new GetBackupSsoRequest(tagUuid: $tag);

        $this->gateway = self::resolve(ProvisionGateway::class);
        $result = $this->gateway->request($request);

        self::assertInstanceOf(BackupSsoResult::class, $result);
        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNotNull($result->ssoUrl);
        self::assertStringContainsString($this->expectedSsoUrl($responseOtt, $acronisBackupDeployment->acronisProvider->sso_target_url), $result->ssoUrl);
        self::assertNull($result->exception);

        $savedRequest = $this->requestRepository->findById($request->requestId);
        self::assertNotNull($savedRequest);

        self::assertSame(ProvisionType::BACKUP, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::GET_BACKUP_SSO_REQUEST, $savedRequest->request_name);
        self::assertSame(
            '[]',
            $savedRequest->request_data
        );

        $savedResult = $this->resultRepository
            ->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedRequest->uuid), 1)
            ->first();

        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::SUCCESS, $savedResult->status);
    }

    #[Test]
    public function getSsoRequestValidationFails(): void
    {
        $tag = Uuid::uuid4();

        $request = new GetBackupSsoRequest(
            tagUuid: $tag,
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

        $savedRequest = $this->requestRepository->findById($request->requestId);
        self::assertNotNull($savedRequest);

        self::assertSame(ProvisionType::BACKUP, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::GET_BACKUP_SSO_REQUEST, $savedRequest->request_name);
        self::assertSame(
            '[]',
            $savedRequest->request_data
        );

        $savedResult = $this->resultRepository
            ->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedRequest->uuid), 1)
            ->first();

        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::VALIDATION_ERROR, $savedResult->status);
    }

    #[Test]
    public function getSsoRequestFailsExternal(): void
    {
        $tag = Uuid::uuid4();

        $acronisBackupDeployment = AcronisBackupDeploymentFactory::new()
            ->for(
                BackupDeploymentFactory::new()
                    ->for(
                        ProvisioningRequestFactory::new()
                            ->backup()
                            ->state([
                                'request_name' => ProvisionRequestName::CREATE_BACKUP,
                                'tag' => $tag,
                            ])
                            ->has(ProvisioningResultFactory::new()->success(), 'result'),
                        'request'
                    )
            )
            ->createOne();

        $expectedException = new SaloonException('Something went wrong');

        $userClient = self::createMock(AcronisUserClient::class);
        $userClient
            ->expects(self::once())
            ->method('getSso')
            ->with($acronisBackupDeployment->user_uuid->toString())
            ->willThrowException($expectedException);

        $acronisClientFactory = self::createMock(AcronisClientFactory::class);
        $acronisClientFactory
            ->expects(self::once())
            ->method('createFromDeployment')
            ->with(self::isInstanceOf(AcronisBackupDeployment::class))
            ->willReturn(new AcronisClient(
                tenantId: Uuid::uuid4(),
                userClient: $userClient,
                offeringItemsClient: self::createStub(AcronisOfferingItemsClient::class),
                tenantClient: self::createStub(AcronisTenantClient::class),
                genericClient: self::createStub(AcronisGenericClient::class),
            ));

        self::instance(AcronisClientFactory::class, $acronisClientFactory);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $request = new GetBackupSsoRequest(tagUuid: $tag);

        $this->gateway = self::resolve(ProvisionGateway::class);
        $result = $this->gateway->request($request);

        self::assertInstanceOf(BackupSsoResult::class, $result);
        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($expectedException, $result->exception);

        $savedRequest = $this->requestRepository->findById($request->requestId);
        self::assertNotNull($savedRequest);

        self::assertSame(ProvisionType::BACKUP, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::GET_BACKUP_SSO_REQUEST, $savedRequest->request_name);
        self::assertSame(
            '[]',
            $savedRequest->request_data
        );

        $savedResult = $this->resultRepository
            ->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedRequest->uuid), 1)
            ->first();

        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::FAILED, $savedResult->status);
    }

    private function expectedSsoUrl(string $ott, string $targetUrl): string
    {
        return sprintf('/idp/external-login#ott=%s&targetURI=%s', rawurlencode($ott), $targetUrl);
    }
}
