<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Sitebuilder\Integration;

use Illuminate\Database\LostConnectionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use SandwaveIo\BaseKit\BaseKit;
use Tests\Factories\BasekitContextFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;
use Waterfront\Domain\Provision\Models\ProvisioningResult;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Domain\Provision\Repositories\ProvisioningResultRepository;
use Waterfront\Domain\Provision\Services\ProvisionTraceabilityService;
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitContext;
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitSitebuilderDeployment;
use Waterfront\Domain\Provision\Sitebuilder\Models\SitebuilderDeployment;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\SitebuilderDeploymentRepository;
use Waterfront\Domain\Provision\Sitebuilder\Requests\CreateBasekitDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Sitebuilder\Results\SitebuilderResult;
use Waterfront\Domain\Provision\Sitebuilder\Services\CreateBasekitService;
use Waterfront\Infra\Basekit\Config\ConnectorConfig;

#[CoversClass(CreateBasekitDeploymentsFromMigrationRequest::class)]
#[CoversClass(ProvisionGateway::class)]
#[CoversClass(ProvisionTraceabilityService::class)]
#[CoversClass(ProvisioningResultRepository::class)]
#[CoversClass(ProvisioningRequestRepository::class)]
#[CoversClass(CreateBasekitService::class)]
class CreateBasekitForMigrationIntegrationTest extends IntegrationTestCase
{
    private const int BRAND_REFERENCE = 1234;
    private const int USER_REF = 1111;
    private const int SITE_REF = 2222;
    private const string DOMAIN = 'example.com';

    private ProvisionGateway $gateway;

    private ProvisioningRequestRepository $requestRepository;

    public function setUp(): void
    {
        parent::setUp();

        $username = 'username';
        $password = 'password';
        $ssoUrl = $baseUrl = 'https://api.basekit.com';

        $this->app->singleton(fn (): ConnectorConfig => new ConnectorConfig(
            baseUrl: $baseUrl,
            ssoUrl: $ssoUrl,
            username: $username,
            password: $password,
            brandReference: self::BRAND_REFERENCE,
        ));

        $baseKit = new BaseKit($username, $password);
        $this->app->bind(BaseKit::class, fn () => $baseKit);

        $this->gateway = self::resolve(ProvisionGateway::class);
        $this->requestRepository = self::resolve(ProvisioningRequestRepository::class);
    }

    #[Test]
    public function createBasekitFromMigrationSuccessfullWhenContextDoesNotExistYet(): void
    {
        $provisionRequest = new CreateBasekitDeploymentsFromMigrationRequest(
            domain: $domain = self::DOMAIN,
            userRef: $userRef = self::USER_REF,
            siteRef: $siteRef = self::SITE_REF,
            context: $context = Uuid::uuid4(),
        );

        $provisionRequest->provider = ProvisionProvider::BASEKIT;

        self::assertDatabaseCount(ProvisioningResult::class, 0);
        self::assertDatabaseCount(ProvisioningRequest::class, 0);
        self::assertDatabaseCount(BasekitContext::class, 0);
        self::assertDatabaseCount(SitebuilderDeployment::class, 0);
        self::assertDatabaseCount(BasekitSitebuilderDeployment::class, 0);

        $provisionResult = $this->gateway->request($provisionRequest);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        self::assertDatabaseCount(BasekitContext::class, 1);

        self::assertDatabaseCount(SitebuilderDeployment::class, 1);
        self::assertDatabaseCount(BasekitSitebuilderDeployment::class, 1);

        self::assertInstanceOf(SitebuilderResult::class, $provisionResult);
        self::assertSame(ProvisionStatus::SUCCESS, $provisionResult->provisionStatus);

        self::assertNull($provisionResult->exception);

        $savedRequest = $this->requestRepository->findById($provisionRequest->requestId);

        self::assertNotNull($savedRequest);
        self::assertJson($savedRequest->request_data);

        self::assertNotNull($savedRequest->context_uuid);
        self::assertTrue($savedRequest->context_uuid->equals($context));
        self::assertSame(ProvisionType::SITEBUILDER, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::CREATE_BASEKIT_DEPLOYMENTS_FROM_MIGRATION, $savedRequest->request_name);
        self::assertSame(ProvisionProvider::BASEKIT, $savedRequest->provision_provider);

        self::assertSame(
            $savedRequest->request_data,
            sprintf(
                '{"domain": "%s", "siteRef": %d, "userRef": %d}',
                $domain,
                $siteRef,
                $userRef,
            )
        );
    }

    #[Test]
    public function createBasekitFromMigrationSuccessfullUserAlreadyExists(): void
    {
        $basekitContext = BasekitContextFactory::new()->createOne();

        $provisionRequest = new CreateBasekitDeploymentsFromMigrationRequest(
            domain: $domain = self::DOMAIN,
            userRef: $userRef = $basekitContext->user_ref,
            siteRef: $siteRef = self::SITE_REF,
            context: $basekitContext->context_uuid,
        );

        $provisionRequest->provider = ProvisionProvider::BASEKIT;

        self::assertDatabaseCount(BasekitContext::class, 1);
        self::assertDatabaseCount(SitebuilderDeployment::class, 0);
        self::assertDatabaseCount(BasekitSitebuilderDeployment::class, 0);

        $provisionResult = $this->gateway->request($provisionRequest);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        self::assertDatabaseCount(BasekitContext::class, 1);

        self::assertDatabaseCount(SitebuilderDeployment::class, 1);
        self::assertDatabaseCount(BasekitSitebuilderDeployment::class, 1);

        self::assertInstanceOf(SitebuilderResult::class, $provisionResult);
        self::assertSame(ProvisionStatus::SUCCESS, $provisionResult->provisionStatus);

        self::assertNull($provisionResult->exception);

        $savedRequest = $this->requestRepository->findById($provisionRequest->requestId);
        self::assertNotNull($savedRequest);
        self::assertJson($savedRequest->request_data);

        self::assertNotNull($savedRequest->context_uuid);
        self::assertTrue($savedRequest->context_uuid->equals($basekitContext->context_uuid));
        self::assertSame(ProvisionType::SITEBUILDER, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::CREATE_BASEKIT_DEPLOYMENTS_FROM_MIGRATION, $savedRequest->request_name);
        self::assertSame(ProvisionProvider::BASEKIT, $savedRequest->provision_provider);

        self::assertSame(
            $savedRequest->request_data,
            sprintf(
                '{"domain": "%s", "siteRef": %d, "userRef": %d}',
                $domain,
                $siteRef,
                $userRef,
            )
        );
    }

    #[Test]
    public function createBasekitFromMigrationDatabaseFailureIsStoredAsFailedResult(): void
    {
        $provisionRequest = new CreateBasekitDeploymentsFromMigrationRequest(
            domain: self::DOMAIN,
            userRef: self::USER_REF,
            siteRef: self::SITE_REF,
            context: Uuid::uuid4(),
        );

        $provisionRequest->provider = ProvisionProvider::BASEKIT;

        $sitebuilderDeploymentRepository = $this->createStub(SitebuilderDeploymentRepository::class);

        $sitebuilderDeploymentRepository
            ->method('create')
            ->willThrowException(new LostConnectionException('DB connection error'));

        self::instance(SitebuilderDeploymentRepository::class, $sitebuilderDeploymentRepository);

        $this->gateway = self::resolve(ProvisionGateway::class);
        $provisionResult = $this->gateway->request($provisionRequest);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        self::assertDatabaseCount(BasekitContext::class, 0);
        self::assertDatabaseCount(SitebuilderDeployment::class, 0);
        self::assertDatabaseCount(BasekitSitebuilderDeployment::class, 0);

        self::assertInstanceOf(SitebuilderResult::class, $provisionResult);
        self::assertSame(ProvisionStatus::FAILED, $provisionResult->provisionStatus);

        self::assertNotNull($provisionResult->exception);
    }
}
