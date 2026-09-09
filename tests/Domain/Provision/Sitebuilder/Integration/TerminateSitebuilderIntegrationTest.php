<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Sitebuilder\Integration;

use Illuminate\Contracts\Foundation\Application;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use SandwaveIo\BaseKit\Api\Interfaces\SitesApiInterface;
use SandwaveIo\BaseKit\Api\Interfaces\UserApiInterface;
use SandwaveIo\BaseKit\BaseKit;
use SandwaveIo\BaseKit\Exceptions\BaseKitRequestException;
use Tests\Factories\BasekitSitebuilderDeploymentFactory;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\Factories\ProvisioningResultFactory;
use Tests\Factories\SitebuilderContextBasekitFactory;
use Tests\Factories\SitebuilderDeploymentFactory;
use Tests\IntegrationTestCase;
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
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitContext;
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitSitebuilderDeployment;
use Waterfront\Domain\Provision\Sitebuilder\Models\SitebuilderDeployment;
use Waterfront\Domain\Provision\Sitebuilder\Requests\TerminateSitebuilderContextRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\TerminateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Results\SitebuilderResult;
use Waterfront\Infra\Basekit\Config\ConnectorConfig;
use Waterfront\Infra\Translation\Translator;

#[CoversClass(TerminateSitebuilderRequest::class)]
#[CoversClass(TerminateSitebuilderContextRequest::class)]
#[CoversClass(ProvisionGateway::class)]
#[CoversClass(ProvisionTraceabilityService::class)]
#[CoversClass(ProvisioningResultRepository::class)]
#[CoversClass(ProvisioningRequestRepository::class)]
class TerminateSitebuilderIntegrationTest extends IntegrationTestCase
{
    private ProvisionGateway $gateway;

    private SitesApiInterface&MockInterface $mockSitesApi;

    private UserApiInterface&MockInterface $mockUserApi;

    private ProvisioningResultRepository $resultRepository;

    private ProvisioningRequestRepository $requestRepository;

    public function setUp(): void
    {
        parent::setUp();

        $mockSitesApi = self::mock(SitesApiInterface::class);
        $this->mockSitesApi = $mockSitesApi;

        $mockUserApi = self::mock(UserApiInterface::class);
        $this->mockUserApi = $mockUserApi;

        $this->app->singleton(fn (): ConnectorConfig => new ConnectorConfig(
            baseUrl: 'https://api.basekit.com/',
            ssoUrl: 'https://flow.basekit.com/',
            username: 'username',
            password: 'password',
            brandReference: 1337,
        ));

        $this->app->singleton(function (Application $app) use ($mockSitesApi, $mockUserApi): BaseKit {
            $basekit = new BaseKit(
                username: 'username',
                password: 'password',
                baseUrl: 'https://api.basekit.com/',
                logger: $app->make(LoggerInterface::class),
            );
            $basekit->sitesApi = $mockSitesApi;
            $basekit->userApi = $mockUserApi;
            return $basekit;
        });

        $this->gateway = $this->app->make(ProvisionGateway::class);
        $this->resultRepository = $this->app->make(ProvisioningResultRepository::class);
        $this->requestRepository = $this->app->make(ProvisioningRequestRepository::class);
    }

    #[Test]
    public function terminateSitebuilderRequest(): void
    {
        $contextUuid = Uuid::uuid4();
        $tag = Uuid::uuid4();
        $originRequestUuid = Uuid::uuid4();
        $siteRef = 7331;

        SitebuilderContextBasekitFactory::new()->createOne([
            'context_uuid' => $contextUuid,
            'user_ref' => 42,
        ]);

        $basekit = BasekitSitebuilderDeploymentFactory::new()
            ->for(
                SitebuilderDeploymentFactory::new()
                    ->for(
                        ProvisioningRequestFactory::new()
                            ->sitebuilder()
                            ->state([
                                'request_name' => ProvisionRequestName::CREATE_SITEBUILDER,
                                'context_uuid' => $contextUuid,
                                'uuid' => $originRequestUuid,
                                'tag' => $tag,
                            ])
                            ->has(ProvisioningResultFactory::new()->success(), 'result'),
                        'request'
                    )
            )
            ->createOne(['site_ref' => $siteRef]);

        $this->mockSitesApi
            ->expects('hardDelete')
            ->once()
            ->with($siteRef)
            ->andReturnNull();

        $request = new TerminateSitebuilderRequest(
            context: $contextUuid,
            tagUuid: $tag
        );

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertInstanceOf(SitebuilderResult::class, $result);
        self::assertNull($result->exception);

        $savedRequest = $this->requestRepository->findById($request->requestId);
        self::assertNotNull($savedRequest);

        self::assertNotNull($savedRequest->context_uuid);
        self::assertTrue($savedRequest->context_uuid->equals($contextUuid));
        self::assertSame(ProvisionType::SITEBUILDER, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::TERMINATE_SITEBUILDER_SITE, $savedRequest->request_name);
        self::assertSame(
            '[]',
            $savedRequest->request_data
        );

        $savedResult = $this->resultRepository
            ->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedRequest->uuid), 1)
            ->first();
        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::SUCCESS, $savedResult->status);

        self::assertSoftDeleted('sitebuilder_deployments_basekit', [
            'id' => $basekit->id,
        ]);
        self::assertSoftDeleted('sitebuilder_deployments', [
            'id' => $basekit->sitebuilder_deployment_id,
        ]);
    }

    #[Test]
    public function terminateSitebuilderRequestValidationFails(): void
    {
        $translator = $this->app->make(Translator::class);
        $doesntExistsMessage = $translator->translate('validation.exists');

        $contextUuid = Uuid::uuid4();
        $tag = Uuid::uuid4();

        $request = new TerminateSitebuilderRequest(
            context: $contextUuid,
            tagUuid: $tag
        );

        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::VALIDATION_ERROR, $result->provisionStatus);
        self::assertInstanceOf(ProvisionResult::class, $result);
        self::assertNull($result->exception);
        self::assertNotNull($result->validationResult?->messages);

        self::assertCount(2, $result->validationResult->messages);
        self::assertArrayHasKey('context', $result->validationResult->messages);
        self::assertArrayHasKey('tag', $result->validationResult->messages);
        self::assertSame([$doesntExistsMessage], $result->validationResult->messages['context']);
        self::assertSame(['No create request with this tag in the [sitebuilder] type.'], $result->validationResult->messages['tag']);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $savedRequest = $this->requestRepository->findById($request->requestId);
        self::assertNotNull($savedRequest);
        self::assertNotNull($savedRequest->context_uuid);
        self::assertTrue($savedRequest->context_uuid->equals($contextUuid));
        self::assertSame(ProvisionType::SITEBUILDER, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::TERMINATE_SITEBUILDER_SITE, $savedRequest->request_name);
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
    public function terminateSitebuilderRequestFailsExternal(): void
    {
        $contextUuid = Uuid::uuid4();
        $tag = Uuid::uuid4();
        $siteRef = 7331;

        SitebuilderContextBasekitFactory::new()->createOne([
            'context_uuid' => $contextUuid,
            'user_ref' => 42,
        ]);

        $basekit = BasekitSitebuilderDeploymentFactory::new()
            ->for(
                SitebuilderDeploymentFactory::new()
                    ->for(
                        ProvisioningRequestFactory::new()
                            ->sitebuilder()
                            ->state([
                                'request_name' => ProvisionRequestName::CREATE_SITEBUILDER,
                                'context_uuid' => $contextUuid,
                                'tag' => $tag,
                            ])
                            ->has(ProvisioningResultFactory::new()->success(), 'result'),
                        'request'
                    )
            )
            ->createOne(['site_ref' => $siteRef]);

        $expectedException = new BaseKitRequestException('Something went wrong');

        $this->mockSitesApi
            ->expects('hardDelete')
            ->once()
            ->with($siteRef)
            ->andThrows($expectedException);

        $request = new TerminateSitebuilderRequest(
            context: $contextUuid,
            tagUuid: $tag
        );

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(SitebuilderResult::class, $result);
        self::assertSame($expectedException, $result->exception);

        $savedRequest = $this->requestRepository->findById($request->requestId);
        self::assertNotNull($savedRequest);
        self::assertNotNull($savedRequest->context_uuid);
        self::assertTrue($savedRequest->context_uuid->equals($contextUuid));
        self::assertSame(ProvisionType::SITEBUILDER, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::TERMINATE_SITEBUILDER_SITE, $savedRequest->request_name);
        self::assertSame(
            '[]',
            $savedRequest->request_data
        );

        $savedResult = $this->resultRepository
            ->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedRequest->uuid), 1)
            ->first();
        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::FAILED, $savedResult->status);

        self::assertNotSoftDeleted('sitebuilder_deployments_basekit', [
            'id' => $basekit->id,
        ]);
        self::assertNotSoftDeleted('sitebuilder_deployments', [
            'id' => $basekit->sitebuilder_deployment_id,
        ]);
    }

    #[Test]
    public function terminateSitebuilderByContextRequest(): void
    {
        $contextUuid = Uuid::uuid4();
        $userRef = 42;

        SitebuilderContextBasekitFactory::new()->createOne([
            'context_uuid' => $contextUuid,
            'user_ref' => $userRef,
        ]);

        $basekit1 = BasekitSitebuilderDeploymentFactory::new()
            ->for(
                SitebuilderDeploymentFactory::new()
                    ->for(
                        ProvisioningRequestFactory::new()
                            ->sitebuilder()
                            ->state([
                                'request_name' => ProvisionRequestName::CREATE_SITEBUILDER,
                                'context_uuid' => $contextUuid,
                            ]),
                        'request'
                    )
            )
            ->createOne(['site_ref' => 1234]);

        $basekit2 = BasekitSitebuilderDeploymentFactory::new()
            ->for(
                SitebuilderDeploymentFactory::new()
                    ->for(
                        ProvisioningRequestFactory::new()
                            ->sitebuilder()
                            ->state([
                                'request_name' => ProvisionRequestName::CREATE_SITEBUILDER,
                                'context_uuid' => $contextUuid,
                            ]),
                        'request'
                    )
            )
            ->createOne(['site_ref' => 5678]);

        $shouldNotBeTerminatedContext = Uuid::uuid4();
        $noTerminateUserRef = 8888;
        SitebuilderContextBasekitFactory::new()->createOne([
            'context_uuid' => $shouldNotBeTerminatedContext,
            'user_ref' => $noTerminateUserRef,
        ]);

        $noTerminateDeployments = BasekitSitebuilderDeploymentFactory::new()
            ->for(
                SitebuilderDeploymentFactory::new()
                    ->for(
                        ProvisioningRequestFactory::new()
                            ->sitebuilder()
                            ->state([
                                'request_name' => ProvisionRequestName::CREATE_SITEBUILDER,
                                'context_uuid' => $shouldNotBeTerminatedContext,
                            ]),
                        'request'
                    )
            )
            ->createOne(['site_ref' => 4321]);

        $this->mockUserApi
            ->expects('delete')
            ->once()
            ->with($userRef)
            ->andReturnNull();

        $this->mockUserApi
            ->expects('delete')
            ->never()
            ->with($noTerminateUserRef);

        $request = new TerminateSitebuilderContextRequest(
            context: $contextUuid,
        );

        self::assertDatabaseCount(ProvisioningResult::class, 0);
        self::assertDatabaseCount(ProvisioningRequest::class, 3);
        self::assertDatabaseCount(BasekitContext::class, 2);
        self::assertDatabaseCount(SitebuilderDeployment::class, 3);
        self::assertDatabaseCount(BasekitSitebuilderDeployment::class, 3);

        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertInstanceOf(SitebuilderResult::class, $result);
        self::assertNull($result->exception);

        $savedTerminateRequest = $this->requestRepository->findById($request->requestId);
        self::assertNotNull($savedTerminateRequest);
        self::assertNotNull($savedTerminateRequest->context_uuid);
        self::assertTrue($savedTerminateRequest->context_uuid->equals($contextUuid));
        self::assertSame(ProvisionType::SITEBUILDER, $savedTerminateRequest->request_type);
        self::assertSame(ProvisionRequestName::TERMINATE_SITEBUILDER_CONTEXT, $savedTerminateRequest->request_name);
        self::assertSame(
            '[]',
            $savedTerminateRequest->request_data
        );

        $savedResult = $this->resultRepository
            ->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedTerminateRequest->uuid), 1)
            ->first();
        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::SUCCESS, $savedResult->status);

        self::assertSoftDeleted(BasekitSitebuilderDeployment::class, [
            'id' => $basekit1->id,
        ]);
        self::assertSoftDeleted(SitebuilderDeployment::class, [
            'id' => $basekit1->sitebuilder_deployment_id,
        ]);
        self::assertSoftDeleted(BasekitSitebuilderDeployment::class, [
            'id' => $basekit2->id,
        ]);
        self::assertSoftDeleted(SitebuilderDeployment::class, [
            'id' => $basekit2->sitebuilder_deployment_id,
        ]);
        self::assertSoftDeleted(BasekitContext::class, [
            'context_uuid' => $contextUuid->toString(),
        ]);

        self::assertNotSoftDeleted(BasekitSitebuilderDeployment::class, [
            'id' => $noTerminateDeployments->id,
        ]);
        self::assertNotSoftDeleted(SitebuilderDeployment::class, [
            'id' => $noTerminateDeployments->sitebuilder_deployment_id,
        ]);
        self::assertNotSoftDeleted(BasekitContext::class, [
            'context_uuid' => $shouldNotBeTerminatedContext->toString(),
        ]);
    }
}
