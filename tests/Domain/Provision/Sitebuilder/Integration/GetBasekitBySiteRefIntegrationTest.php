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
use SandwaveIo\BaseKit\BaseKit;
use SandwaveIo\BaseKit\Domain\Domain;
use SandwaveIo\BaseKit\Domain\Site;
use SandwaveIo\BaseKit\Exceptions\BaseKitRequestException;
use Tests\Factories\BasekitSitebuilderDeploymentFactory;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\Factories\ProvisioningResultFactory;
use Tests\Factories\SitebuilderContextBasekitFactory;
use Tests\Factories\SitebuilderDeploymentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\DTO\ProvisioningResultQueryFilters;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;
use Waterfront\Domain\Provision\Models\ProvisioningResult;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Domain\Provision\Repositories\ProvisioningResultRepository;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetBasekitSiteByRefRequest;
use Waterfront\Domain\Provision\Sitebuilder\Results\BasekitSiteResult;
use Waterfront\Infra\Basekit\Config\ConnectorConfig;

#[CoversClass(GetBasekitSiteByRefRequest::class)]
class GetBasekitBySiteRefIntegrationTest extends IntegrationTestCase
{
    private ProvisionGateway $gateway;

    /** @var SitesApiInterface&MockInterface */
    private SitesApiInterface $mockSitesApi;

    private ProvisioningResultRepository $resultRepository;

    private ProvisioningRequestRepository $requestRepository;

    public function setUp(): void
    {
        parent::setUp();

        /** @var SitesApiInterface&MockInterface $mockSitesApi */
        $mockSitesApi = self::mock(SitesApiInterface::class);
        $this->mockSitesApi = $mockSitesApi;

        $this->app->singleton(fn (): ConnectorConfig => new ConnectorConfig(
            baseUrl: 'https://api.basekit.com/',
            ssoUrl: 'https://flow.basekit.com/',
            username: 'username',
            password: 'password',
            brandReference: 1337,
        ));

        $this->app->singleton(function (Application $app) use ($mockSitesApi): BaseKit {
            $basekit = new BaseKit(
                username: 'username',
                password: 'password',
                baseUrl: 'https://api.basekit.com/',
                logger: $app->make(LoggerInterface::class),
            );
            $basekit->sitesApi = $mockSitesApi;
            return $basekit;
        });

        $this->gateway = $this->app->make(ProvisionGateway::class);
        $this->resultRepository = $this->app->make(ProvisioningResultRepository::class);
        $this->requestRepository = $this->app->make(ProvisioningRequestRepository::class);
    }

    #[Test]
    public function getBasekitBySiteRefRequest(): void
    {
        $contextUuid = Uuid::uuid4();
        $tag = Uuid::uuid4();
        $originRequestUuid = Uuid::uuid4();
        $siteRef = 7331;
        $domain = 'sandwave.io';

        SitebuilderContextBasekitFactory::new()->createOne([
            'context_uuid' => $contextUuid,
            'user_ref' => 42,
        ]);

        BasekitSitebuilderDeploymentFactory::new()
            ->for(
                SitebuilderDeploymentFactory::new()
                    ->for(
                        ProvisioningRequestFactory::new()
                            ->sitebuilder()
                            ->state([
                                'request_name' => ProvisionRequestName::GET_BASEKIT_SITE_BY_REF_REQUEST,
                                'context_uuid' => $contextUuid,
                                'uuid' => $originRequestUuid,
                                'tag' => $tag,
                            ])
                            ->has(ProvisioningResultFactory::new()->success(), 'result'),
                        'request'
                    )
            )
            ->createOne(['site_ref' => $siteRef]);

        $basekitSite = new Site(
            $siteRef,
            [],
            null,
            null,
            new Domain($siteRef, $domain),
            null,
            0,
            0,
            true,
            null,
            null,
            true,
            null,
        );

        $this->mockSitesApi
            ->expects('get')
            ->once()
            ->with($siteRef)
            ->andReturn($basekitSite);

        $request = new GetBasekitSiteByRefRequest(
            context: $contextUuid,
            siteRef: $siteRef
        );

        $request->provider = ProvisionProvider::BASEKIT;

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertInstanceOf(BasekitSiteResult::class, $result);
        self::assertNull($result->exception);

        $savedRequest = $this->requestRepository->findById($request->requestId);
        self::assertNotNull($savedRequest);
        self::assertNotNull($savedRequest->context_uuid);
        self::assertTrue($savedRequest->context_uuid->equals($contextUuid));
        self::assertSame(ProvisionType::SITEBUILDER, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::GET_BASEKIT_SITE_BY_REF_REQUEST, $savedRequest->request_name);
        self::assertSame(
            $savedRequest->request_data,
            sprintf(
                '{"siteRef": %d}',
                $siteRef
            )
        );

        $savedResult = $this->resultRepository
            ->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedRequest->uuid), 1)
            ->first();
        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::SUCCESS, $savedResult->status);
    }

    #[Test]
    public function getBasekitBySiteRefRequestFailsExternal(): void
    {
        $contextUuid = Uuid::uuid4();
        $tag = Uuid::uuid4();
        $siteRef = 7331;

        SitebuilderContextBasekitFactory::new()->createOne([
            'context_uuid' => $contextUuid,
            'user_ref' => 42,
        ]);

        BasekitSitebuilderDeploymentFactory::new()
            ->for(
                SitebuilderDeploymentFactory::new()
                    ->for(
                        ProvisioningRequestFactory::new()
                            ->sitebuilder()
                            ->state([
                                'request_name' => ProvisionRequestName::GET_BASEKIT_SITE_BY_REF_REQUEST,
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
            ->expects('get')
            ->once()
            ->with($siteRef)
            ->andThrow($expectedException);

        $request = new GetBasekitSiteByRefRequest(
            context: $contextUuid,
            siteRef: $siteRef
        );

        $request->provider = ProvisionProvider::BASEKIT;

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(BasekitSiteResult::class, $result);
        self::assertSame($expectedException, $result->exception);

        $savedRequest = $this->requestRepository->findById($request->requestId);
        self::assertNotNull($savedRequest);
        self::assertNotNull($savedRequest->context_uuid);
        self::assertTrue($savedRequest->context_uuid->equals($contextUuid));
        self::assertSame(ProvisionType::SITEBUILDER, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::GET_BASEKIT_SITE_BY_REF_REQUEST, $savedRequest->request_name);
        self::assertSame(
            $savedRequest->request_data,
            sprintf(
                '{"siteRef": %d}',
                $siteRef
            )
        );

        $savedResult = $this->resultRepository
            ->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedRequest->uuid), 1)
            ->first();
        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::FAILED, $savedResult->status);
    }
}
