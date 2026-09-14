<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Sitebuilder\Services;

use Illuminate\Database\Eloquent\Collection;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use SandwaveIo\BaseKit\Api\Interfaces\LoginApiInterface;
use SandwaveIo\BaseKit\Api\Interfaces\SitesApiInterface;
use SandwaveIo\BaseKit\Api\Interfaces\UserApiInterface;
use SandwaveIo\BaseKit\BaseKit;
use SandwaveIo\BaseKit\Domain\AccountHolder;
use SandwaveIo\BaseKit\Domain\Capabilities;
use SandwaveIo\BaseKit\Domain\Domain;
use SandwaveIo\BaseKit\Domain\Site;
use SandwaveIo\BaseKit\Exceptions\BaseKitRequestException;
use SandwaveIo\BaseKit\Exceptions\UnexpectedValueException;
use Tests\Factories\BasekitSitebuilderDeploymentFactory;
use Tests\Factories\SitebuilderContextBasekitFactory;
use Tests\Factories\SitebuilderDeploymentFactory;
use Tests\TestCase;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Exceptions\DeploymentNotFoundException;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\BasekitSiteRefNotFoundException;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\BasekitUserRefNotFoundForContextException;
use Waterfront\Domain\Provision\Sitebuilder\Models\SitebuilderDeployment;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\BasekitContextRepository;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\SitebuilderDeploymentRepository;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetBasekitSiteByRefRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetBasekitUserByRefRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetSitebuilderSsoRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\TerminateSitebuilderContextRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\TerminateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Services\BasekitProvisionService;
use Waterfront\Domain\Provision\Sitebuilder\Services\CreateBasekitService;
use Waterfront\Domain\Provision\Sitebuilder\Services\DeleteBasekitService;
use Waterfront\Domain\Provision\Sitebuilder\Services\UpdateBasekitService;
use Waterfront\Infra\Basekit\Config\ConnectorConfig;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(BasekitProvisionService::class)]
class BasekitProvisionServiceTest extends TestCase
{
    private SitebuilderDeploymentRepository&MockInterface $mockSitebuilderRepository;

    private BasekitContextRepository&MockInterface $mockBasekitContextRepository;

    private LoggerInterface&MockInterface $mockLogger;

    private BaseKit $mockBasekitClient;

    private ConnectorConfig $basekitConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $username = 'user';
        $password = 'pass';
        $baseUrl = 'https://api.basekit.com';

        $this->mockBasekitClient = new BaseKit(
            username: $username,
            password: $password,
            baseUrl: $baseUrl,
            logger: $this->app->make(LoggerInterface::class),
        );

        $this->basekitConfig = new ConnectorConfig(
            baseUrl: $baseUrl,
            ssoUrl: 'https://test-sso.basekit.com',
            username: 'user',
            password: 'pass',
            brandReference: 1337,
        );

        $sitebuilderRepo = self::mock(SitebuilderDeploymentRepository::class);
        $this->mockSitebuilderRepository = $sitebuilderRepo;

        $basekitcontextRepository = self::mock(BasekitContextRepository::class);
        $this->mockBasekitContextRepository = $basekitcontextRepository;

        $logger = self::mock(LoggerInterface::class);
        $this->mockLogger = $logger;

        // Make sure laravel doesn't try to save relations to the database in our unit test.
        SitebuilderContextBasekitFactory::dontExpandRelationshipsByDefault();
        BasekitSitebuilderDeploymentFactory::dontExpandRelationshipsByDefault();
        SitebuilderDeploymentFactory::dontExpandRelationshipsByDefault();
    }

    #[Test]
    public function getSsoReturnsUrl(): void
    {
        $tag = Uuid::uuid4();
        $ssoRequest = new GetSitebuilderSsoRequest(
            context: Uuid::uuid4(),
            tagUuid: $tag,
        );
        $basekitContext = SitebuilderContextBasekitFactory::new()->makeOne();
        $basekitSitebuilderDeployment = BasekitSitebuilderDeploymentFactory::new()->makeOne();

        $sitebuilderDeployment = SitebuilderDeploymentFactory::new()->makeOne();

        $sitebuilderDeployment->setRelation('basekitDeployment', $basekitSitebuilderDeployment);
        $sitebuilderDeployment->setRelation('basekitContext', $basekitContext);

        $this->mockSitebuilderRepository->expects('findByTag')->once()->with($tag)->andReturn($sitebuilderDeployment);

        $mockLoginApi = self::mock(LoginApiInterface::class);
        $this->mockBasekitClient->loginApi = $mockLoginApi;

        $mockLoginApi->expects('autoLogin')->once()->with($basekitContext->user_ref)->andReturn('login-hash');

        $service = new BasekitProvisionService(
            createBasekitService: self::createStub(CreateBasekitService::class),
            basekitClient: $this->mockBasekitClient,
            basekitConfig: $this->basekitConfig,
            sitebuilderDeploymentRepository: $this->mockSitebuilderRepository,
            logger: $this->mockLogger,
            updateBasekitService: self::createStub(UpdateBasekitService::class),
            deleteBasekitService: self::createStub(DeleteBasekitService::class),
        );

        $result = $service->getSso($ssoRequest);

        $expectedSso = sprintf(
            '%s/login?hash=%s&siteRef=%s',
            $this->basekitConfig->ssoUrl,
            rawurlencode('login-hash'),
            $basekitSitebuilderDeployment->site_ref,
        );

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertIsString($result->ssoUrl);
        self::assertSame($expectedSso, $result->ssoUrl);
    }

    #[Test]
    public function getSsoReturnsFailedExternalError(): void
    {
        $externalException = new UnexpectedValueException('External error');
        $tag = Uuid::uuid4();
        $ssoRequest = new GetSitebuilderSsoRequest(
            context: Uuid::uuid4(),
            tagUuid: $tag,
        );

        $basekitContext = SitebuilderContextBasekitFactory::new()->makeOne();
        $basekitSitebuilderDeployment = BasekitSitebuilderDeploymentFactory::new()->makeOne();

        $sitebuilderDeployment = SitebuilderDeploymentFactory::new()->makeOne();

        $sitebuilderDeployment->setRelation('basekitDeployment', $basekitSitebuilderDeployment);
        $sitebuilderDeployment->setRelation('basekitContext', $basekitContext);

        $this->mockSitebuilderRepository->expects('findByTag')->once()->with($tag)->andReturn($sitebuilderDeployment);

        $mockLoginApi = self::mock(LoginApiInterface::class);
        $this->mockBasekitClient->loginApi = $mockLoginApi;

        $mockLoginApi->expects('autoLogin')->once()->with($basekitContext->user_ref)->andThrow($externalException);

        $service = new BasekitProvisionService(
            createBasekitService: self::createStub(CreateBasekitService::class),
            basekitClient: $this->mockBasekitClient,
            basekitConfig: $this->basekitConfig,
            sitebuilderDeploymentRepository: $this->mockSitebuilderRepository,
            logger: $this->mockLogger,
            updateBasekitService: self::createStub(UpdateBasekitService::class),
            deleteBasekitService: self::createStub(DeleteBasekitService::class),
        );

        $result = $service->getSso($ssoRequest);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($result->exception, $externalException);
    }

    #[Test]
    public function getSsoDeploymentNotFound(): void
    {
        $tag = Uuid::uuid4();
        $ssoRequest = new GetSitebuilderSsoRequest(
            context: Uuid::uuid4(),
            tagUuid: $tag,
        );

        $this->mockSitebuilderRepository->expects('findByTag')->once()->with($tag)->andReturn(null);

        $service = new BasekitProvisionService(
            createBasekitService: self::createStub(CreateBasekitService::class),
            basekitClient: $this->mockBasekitClient,
            basekitConfig: $this->basekitConfig,
            sitebuilderDeploymentRepository: $this->mockSitebuilderRepository,
            logger: $this->mockLogger,
            updateBasekitService: self::createStub(UpdateBasekitService::class),
            deleteBasekitService: self::createStub(DeleteBasekitService::class),
        );

        $result = $service->getSso($ssoRequest);

        self::assertInstanceOf(DeploymentNotFoundException::class, $result->exception);
        self::assertSame(
            sprintf('No sitebuilder deployment found for the given tag [%s].', $tag),
            $result->exception->getMessage(),
        );
    }

    #[Test]
    public function getSsoMissingSiteRef(): void
    {
        $tag = Uuid::uuid4();
        $ssoRequest = new GetSitebuilderSsoRequest(
            context: Uuid::uuid4(),
            tagUuid: $tag,
        );

        $sitebuilderDeployment = SitebuilderDeploymentFactory::new()->makeOne();

        $this->mockSitebuilderRepository->expects('findByTag')->once()->with($tag)->andReturn($sitebuilderDeployment);

        $service = new BasekitProvisionService(
            createBasekitService: self::createStub(CreateBasekitService::class),
            basekitClient: $this->mockBasekitClient,
            basekitConfig: $this->basekitConfig,
            sitebuilderDeploymentRepository: $this->mockSitebuilderRepository,
            logger: $this->mockLogger,
            updateBasekitService: self::createStub(UpdateBasekitService::class),
            deleteBasekitService: self::createStub(DeleteBasekitService::class),
        );

        $result = $service->getSso($ssoRequest);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(BasekitSiteRefNotFoundException::class, $result->exception);
    }

    #[Test]
    public function getSsoMissingContext(): void
    {
        $tag = Uuid::uuid4();
        $ssoRequest = new GetSitebuilderSsoRequest(
            context: Uuid::uuid4(),
            tagUuid: $tag,
        );

        $basekitSitebuilderDeployment = BasekitSitebuilderDeploymentFactory::new()->makeOne();
        $sitebuilderDeployment = SitebuilderDeploymentFactory::new()->makeOne();

        $sitebuilderDeployment->setRelation('basekitDeployment', $basekitSitebuilderDeployment);

        $this->mockSitebuilderRepository->expects('findByTag')->once()->with($tag)->andReturn($sitebuilderDeployment);

        $service = new BasekitProvisionService(
            createBasekitService: self::createStub(CreateBasekitService::class),
            basekitClient: $this->mockBasekitClient,
            basekitConfig: $this->basekitConfig,
            sitebuilderDeploymentRepository: $this->mockSitebuilderRepository,
            logger: $this->mockLogger,
            updateBasekitService: self::createStub(UpdateBasekitService::class),
            deleteBasekitService: self::createStub(DeleteBasekitService::class),
        );

        $result = $service->getSso($ssoRequest);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(BasekitUserRefNotFoundForContextException::class, $result->exception);
    }

    #[Test]
    public function terminateSitebuilderSuccess(): void
    {
        $tag = Uuid::uuid4();
        $terminateRequest = new TerminateSitebuilderRequest(
            context: Uuid::uuid4(),
            tagUuid: $tag,
        );

        $terminateRequest->requestId = 1234;
        $terminateRequest->provider = ProvisionProvider::BASEKIT;

        $basekitSitebuilderDeployment = BasekitSitebuilderDeploymentFactory::new()->makeOne();

        $sitebuilderDeployment = SitebuilderDeploymentFactory::new()->makeOne();

        $sitebuilderDeployment->setRelation('basekitDeployment', $basekitSitebuilderDeployment);

        $this->mockSitebuilderRepository->expects('findByTag')->once()->with($tag)->andReturn($sitebuilderDeployment);

        $mockSitesApi = self::mock(SitesApiInterface::class);
        $this->mockBasekitClient->sitesApi = $mockSitesApi;

        $mockSitesApi->expects('hardDelete')->once()->with($basekitSitebuilderDeployment->site_ref);

        $this->mockSitebuilderRepository
            ->expects('deleteSitebuilderAndChildren')
            ->once()
            ->with($sitebuilderDeployment)
            ->andReturn(true);

        $this->mockLogger->expects('debug')->with('Terminating single basekit sitebuilder site', [
            LoggingContextKeys::PROVISIONING_CONTEXT => $terminateRequest->context,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
            LoggingContextKeys::PROVISIONING_REQUEST_ID => $terminateRequest->requestId,
            LoggingContextKeys::DOMAIN_NAME => $sitebuilderDeployment->domain,
            LoggingContextKeys::META => [
                'site_ref' => $sitebuilderDeployment->basekitDeployment?->site_ref,
            ],
        ]);

        $deleteBasekitService = new DeleteBasekitService(
            logger: $this->mockLogger,
            baseKitContextRepository: $this->mockBasekitContextRepository,
            sitebuilderDeploymentRepository: $this->mockSitebuilderRepository,
            basekitClient: $this->mockBasekitClient,
        );

        $service = new BasekitProvisionService(
            createBasekitService: self::createStub(CreateBasekitService::class),
            basekitClient: $this->mockBasekitClient,
            basekitConfig: $this->basekitConfig,
            sitebuilderDeploymentRepository: $this->mockSitebuilderRepository,
            logger: $this->mockLogger,
            updateBasekitService: self::createStub(UpdateBasekitService::class),
            deleteBasekitService: $deleteBasekitService,
        );

        $result = $service->terminateSitebuilder($terminateRequest);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    #[Test]
    public function terminateSitebuilderFailedExternalError(): void
    {
        $tag = Uuid::uuid4();
        $terminateRequest = new TerminateSitebuilderRequest(
            context: Uuid::uuid4(),
            tagUuid: $tag,
        );
        $terminateRequest->requestId = 1234;

        $basekitSitebuilderDeployment = BasekitSitebuilderDeploymentFactory::new()->makeOne();

        $sitebuilderDeployment = SitebuilderDeploymentFactory::new()->makeOne();
        $sitebuilderDeployment->setRelation('basekitDeployment', $basekitSitebuilderDeployment);

        $this->mockSitebuilderRepository->expects('findByTag')->once()->with($tag)->andReturn($sitebuilderDeployment);

        $mockSitesApi = self::mock(SitesApiInterface::class);
        $this->mockBasekitClient->sitesApi = $mockSitesApi;

        $external = new BaseKitRequestException('kaboom');

        $mockSitesApi
            ->expects('hardDelete')
            ->once()
            ->with($basekitSitebuilderDeployment->site_ref)
            ->andThrow($external);

        $this->mockSitebuilderRepository->expects('deleteSitebuilderAndChildren')->never();

        $deleteBasekitService = new DeleteBasekitService(
            logger: self::createStub(LoggerInterface::class),
            baseKitContextRepository: $this->mockBasekitContextRepository,
            sitebuilderDeploymentRepository: $this->mockSitebuilderRepository,
            basekitClient: $this->mockBasekitClient,
        );

        $service = new BasekitProvisionService(
            createBasekitService: self::createStub(CreateBasekitService::class),
            basekitClient: $this->mockBasekitClient,
            basekitConfig: $this->basekitConfig,
            sitebuilderDeploymentRepository: $this->mockSitebuilderRepository,
            logger: self::createStub(LoggerInterface::class),
            updateBasekitService: self::createStub(UpdateBasekitService::class),
            deleteBasekitService: $deleteBasekitService,
        );

        $result = $service->terminateSitebuilder($terminateRequest);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($external, $result->exception);
    }

    #[Test]
    public function terminateSitebuilderDeploymentNotFound(): void
    {
        $tag = Uuid::uuid4();
        $terminateRequest = new TerminateSitebuilderRequest(
            context: Uuid::uuid4(),
            tagUuid: $tag,
        );

        $this->mockSitebuilderRepository->expects('findByTag')->once()->with($tag)->andReturn(null);

        $this->mockSitebuilderRepository->expects('deleteSitebuilderAndChildren')->never();

        $deleteBasekitService = new DeleteBasekitService(
            logger: self::createStub(LoggerInterface::class),
            baseKitContextRepository: $this->mockBasekitContextRepository,
            sitebuilderDeploymentRepository: $this->mockSitebuilderRepository,
            basekitClient: $this->mockBasekitClient,
        );

        $service = new BasekitProvisionService(
            createBasekitService: self::createStub(CreateBasekitService::class),
            basekitClient: $this->mockBasekitClient,
            basekitConfig: $this->basekitConfig,
            sitebuilderDeploymentRepository: $this->mockSitebuilderRepository,
            logger: $this->mockLogger,
            updateBasekitService: self::createStub(UpdateBasekitService::class),
            deleteBasekitService: $deleteBasekitService,
        );

        $result = $service->terminateSitebuilder($terminateRequest);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(DeploymentNotFoundException::class, $result->exception);
        self::assertSame(
            sprintf('No sitebuilder deployment found for the given tag [%s].', $tag),
            $result->exception->getMessage(),
        );
    }

    #[Test]
    public function terminateSitebuilderMissingSiteRef(): void
    {
        $tag = Uuid::uuid4();
        $terminateRequest = new TerminateSitebuilderRequest(
            context: Uuid::uuid4(),
            tagUuid: $tag,
        );
        $terminateRequest->requestId = 1234;

        $sitebuilderDeployment = SitebuilderDeploymentFactory::new()->makeOne();

        $this->mockSitebuilderRepository->expects('findByTag')->once()->with($tag)->andReturn($sitebuilderDeployment);

        $mockSitesApi = self::mock(SitesApiInterface::class);
        $this->mockBasekitClient->sitesApi = $mockSitesApi;

        $mockSitesApi->expects('hardDelete')->never();

        $this->mockSitebuilderRepository->expects('deleteSitebuilderAndChildren')->never();

        $deleteBasekitService = new DeleteBasekitService(
            logger: self::createStub(LoggerInterface::class),
            baseKitContextRepository: $this->mockBasekitContextRepository,
            sitebuilderDeploymentRepository: $this->mockSitebuilderRepository,
            basekitClient: $this->mockBasekitClient,
        );

        $service = new BasekitProvisionService(
            createBasekitService: self::createStub(CreateBasekitService::class),
            basekitClient: $this->mockBasekitClient,
            basekitConfig: $this->basekitConfig,
            sitebuilderDeploymentRepository: $this->mockSitebuilderRepository,
            logger: self::createStub(LoggerInterface::class),
            updateBasekitService: self::createStub(UpdateBasekitService::class),
            deleteBasekitService: $deleteBasekitService,
        );

        $result = $service->terminateSitebuilder($terminateRequest);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(BasekitSiteRefNotFoundException::class, $result->exception);
    }

    #[Test]
    public function terminateContextUnknownContextUuid(): void
    {
        $context = Uuid::uuid4();
        $this->mockBasekitContextRepository->expects('findByContext')->with($context)->andReturn(null);

        $deleteBasekitService = new DeleteBasekitService(
            logger: self::createStub(LoggerInterface::class),
            baseKitContextRepository: $this->mockBasekitContextRepository,
            sitebuilderDeploymentRepository: $this->mockSitebuilderRepository,
            basekitClient: $this->mockBasekitClient,
        );

        $service = new BasekitProvisionService(
            createBasekitService: self::createStub(CreateBasekitService::class),
            basekitClient: $this->mockBasekitClient,
            basekitConfig: $this->basekitConfig,
            sitebuilderDeploymentRepository: $this->mockSitebuilderRepository,
            logger: $this->mockLogger,
            updateBasekitService: self::createStub(UpdateBasekitService::class),
            deleteBasekitService: $deleteBasekitService,
        );

        $this->mockBasekitContextRepository->expects('delete')->never();

        $request = new TerminateSitebuilderContextRequest($context);
        $result = $service->terminateByContext($request);

        self::assertInstanceOf(TerminateSitebuilderContextRequest::class, $result->provisionData);
        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
    }

    #[Test]
    public function terminateContext(): void
    {
        $context = Uuid::uuid4();

        $deploymentsToTerminate = SitebuilderDeploymentFactory::new()->count(2)->make();

        $basekitContext = SitebuilderContextBasekitFactory::new()->state(['context_uuid' => $context])->makeOne();

        $this->mockBasekitContextRepository->expects('findByContext')->with($context)->andReturn($basekitContext);

        $mockUserApi = self::mock(UserApiInterface::class);
        $this->mockBasekitClient->userApi = $mockUserApi;

        $mockUserApi->expects('delete')->once();

        $this->mockSitebuilderRepository
            ->expects('getSitebuilderDeploymentsByContext')
            ->with($basekitContext)
            ->andReturn($deploymentsToTerminate);

        $firstDeployment = $deploymentsToTerminate->first();

        /**
         * For some reason phpstan fails to understand the type
         * from the factory during the `last` call, ¯\_(ツ)_/¯.
         *
         * @var Collection<int, SitebuilderDeployment> $deploymentsToTerminate
         */
        $secondDeployment = $deploymentsToTerminate->last();

        self::assertNotNull($firstDeployment);
        self::assertNotNull($secondDeployment);

        $this->mockLogger->expects(
            'debug',
        )->with('Removing sitebuilder deployment and basekit deployment with given context.', [
            LoggingContextKeys::DOMAIN_NAME => $firstDeployment->domain,
            LoggingContextKeys::PROVISIONING_ID => $firstDeployment->id,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
            LoggingContextKeys::PROVISIONING_REQUEST_ID => 0,
            LoggingContextKeys::PROVISIONING_CONTEXT => $context,
            LoggingContextKeys::META => [
                'user_ref' => $basekitContext->user_ref,
            ],
        ]);

        $this->mockSitebuilderRepository->expects('deleteSitebuilderAndChildren')->once()->with($firstDeployment);

        $this->mockLogger->expects(
            'debug',
        )->with('Removing sitebuilder deployment and basekit deployment with given context.', [
            LoggingContextKeys::DOMAIN_NAME => $secondDeployment->domain,
            LoggingContextKeys::PROVISIONING_ID => $secondDeployment->id,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
            LoggingContextKeys::PROVISIONING_REQUEST_ID => 0,
            LoggingContextKeys::PROVISIONING_CONTEXT => $context,
            LoggingContextKeys::META => [
                'user_ref' => $basekitContext->user_ref,
            ],
        ]);

        $this->mockSitebuilderRepository->expects('deleteSitebuilderAndChildren')->once()->with($secondDeployment);

        $this->mockBasekitContextRepository
            ->expects('delete')
            ->once()
            ->withArgs(fn (UuidInterface $uuid) => $context->equals($uuid));

        $deleteBasekitService = new DeleteBasekitService(
            logger: $this->mockLogger,
            baseKitContextRepository: $this->mockBasekitContextRepository,
            sitebuilderDeploymentRepository: $this->mockSitebuilderRepository,
            basekitClient: $this->mockBasekitClient,
        );

        $service = new BasekitProvisionService(
            createBasekitService: self::createStub(CreateBasekitService::class),
            basekitClient: $this->mockBasekitClient,
            basekitConfig: $this->basekitConfig,
            sitebuilderDeploymentRepository: $this->mockSitebuilderRepository,
            logger: $this->mockLogger,
            updateBasekitService: self::createStub(UpdateBasekitService::class),
            deleteBasekitService: $deleteBasekitService,
        );

        $request = new TerminateSitebuilderContextRequest($context);
        $request->provider = ProvisionProvider::BASEKIT;
        $result = $service->terminateByContext($request);

        self::assertInstanceOf(TerminateSitebuilderContextRequest::class, $result->provisionData);
        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
    }

    #[Test]
    public function getBasekitSiteByRef(): void
    {
        $siteRef = 123;
        $domain = 'sandwave.io';

        $basekitSiteByRefRequest = new GetBasekitSiteByRefRequest(
            context: Uuid::uuid4(),
            siteRef: $siteRef,
        );

        /** @var MockInterface&SitesApiInterface $mockSitesApi */
        $mockSitesApi = self::mock(SitesApiInterface::class);

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

        $mockSitesApi->expects('get')->once()->with($siteRef)->andReturn($basekitSite);

        $this->mockBasekitClient->sitesApi = $mockSitesApi;

        $service = new BasekitProvisionService(
            createBasekitService: self::createStub(CreateBasekitService::class),
            basekitClient: $this->mockBasekitClient,
            basekitConfig: $this->basekitConfig,
            sitebuilderDeploymentRepository: $this->mockSitebuilderRepository,
            logger: $this->mockLogger,
            updateBasekitService: self::createStub(UpdateBasekitService::class),
            deleteBasekitService: self::createStub(DeleteBasekitService::class),
        );

        $result = $service->getBasekitSiteByRef($basekitSiteByRefRequest);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertSame($siteRef, $result->siteRef);
        self::assertSame($domain, $result->domain);
        self::assertNull($result->exception);
    }

    #[Test]
    public function getBasekitSiteByRefException(): void
    {
        $siteRef = 123;

        $basekitSiteByRefRequest = new GetBasekitSiteByRefRequest(
            context: Uuid::uuid4(),
            siteRef: $siteRef,
        );
        $basekitSiteByRefRequest->requestId = 1234;
        $basekitSiteByRefRequest->provider = ProvisionProvider::BASEKIT;

        /** @var MockInterface&SitesApiInterface $mockSitesApi */
        $mockSitesApi = self::mock(SitesApiInterface::class);

        $external = new BaseKitRequestException('kaboom');

        $mockSitesApi->expects('get')->once()->with($siteRef)->andThrow($external);

        $this->mockBasekitClient->sitesApi = $mockSitesApi;

        $this->mockLogger->expects('error')->with('Unable to get basekit site by ref', [
            LoggingContextKeys::PROVISIONING_CONTEXT => $basekitSiteByRefRequest->context,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
            LoggingContextKeys::PROVISIONING_REQUEST_ID => $basekitSiteByRefRequest->requestId,
            LoggingContextKeys::EXCEPTION => $external,
            LoggingContextKeys::META => [
                'site_ref' => $basekitSiteByRefRequest->siteRef,
            ],
        ]);

        $service = new BasekitProvisionService(
            createBasekitService: self::createStub(CreateBasekitService::class),
            basekitClient: $this->mockBasekitClient,
            basekitConfig: $this->basekitConfig,
            sitebuilderDeploymentRepository: $this->mockSitebuilderRepository,
            logger: $this->mockLogger,
            updateBasekitService: self::createStub(UpdateBasekitService::class),
            deleteBasekitService: self::createStub(DeleteBasekitService::class),
        );

        $result = $service->getBasekitSiteByRef($basekitSiteByRefRequest);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertNotNull($result->exception);
    }

    #[Test]
    public function getBasekitUserByRef(): void
    {
        $userRef = 123;
        $email = 'hobo@sandwave.io';

        $basekitUserByRefRequest = new GetBasekitUserByRefRequest(
            context: Uuid::uuid4(),
            userRef: $userRef,
        );

        /** @var MockInterface&UserApiInterface $mockUserApi */
        $mockUserApi = self::mock(UserApiInterface::class);

        $accountHolder = new AccountHolder(
            $userRef,
            0,
            'jan',
            'man',
            'janman',
            $email,
            0,
            false,
            'NL',
            null,
            null,
            null,
            null,
            null,
            null,
            0,
            0,
            null,
            new Capabilities(),
            null,
            null,
            0,
            '',
            false,
            0,
            null,
            null,
            '',
            null,
        );

        $mockUserApi->expects('get')->once()->with($userRef)->andReturn($accountHolder);

        $this->mockBasekitClient->userApi = $mockUserApi;

        $service = new BasekitProvisionService(
            createBasekitService: self::createStub(CreateBasekitService::class),
            basekitClient: $this->mockBasekitClient,
            basekitConfig: $this->basekitConfig,
            sitebuilderDeploymentRepository: $this->mockSitebuilderRepository,
            logger: $this->mockLogger,
            updateBasekitService: self::createStub(UpdateBasekitService::class),
            deleteBasekitService: self::createStub(DeleteBasekitService::class),
        );

        $result = $service->getBasekitUserByRef($basekitUserByRefRequest);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertSame($userRef, $result->userId);
        self::assertSame($email, $result->email);
        self::assertNull($result->exception);
    }

    #[Test]
    public function getBasekitUserByRefException(): void
    {
        $userRef = 123;

        $basekitUserByRefRequest = new GetBasekitUserByRefRequest(
            context: Uuid::uuid4(),
            userRef: $userRef,
        );

        $basekitUserByRefRequest->requestId = 1234;
        $basekitUserByRefRequest->provider = ProvisionProvider::BASEKIT;

        /** @var MockInterface&UserApiInterface $mockUserApi */
        $mockUserApi = self::mock(UserApiInterface::class);

        $external = new BaseKitRequestException('kaboom');

        $mockUserApi->expects('get')->once()->with($userRef)->andThrow($external);

        $this->mockBasekitClient->userApi = $mockUserApi;

        $this->mockLogger->expects('error')->with('Unable to get basekit user by ref', [
            LoggingContextKeys::PROVISIONING_CONTEXT => $basekitUserByRefRequest->context,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
            LoggingContextKeys::PROVISIONING_REQUEST_ID => $basekitUserByRefRequest->requestId,
            LoggingContextKeys::EXCEPTION => $external,
            LoggingContextKeys::META => [
                'user_ref' => $basekitUserByRefRequest->userRef,
            ],
        ]);

        $service = new BasekitProvisionService(
            createBasekitService: self::createStub(CreateBasekitService::class),
            basekitClient: $this->mockBasekitClient,
            basekitConfig: $this->basekitConfig,
            sitebuilderDeploymentRepository: $this->mockSitebuilderRepository,
            logger: $this->mockLogger,
            updateBasekitService: self::createStub(UpdateBasekitService::class),
            deleteBasekitService: self::createStub(DeleteBasekitService::class),
        );

        $result = $service->getBasekitUserByRef($basekitUserByRefRequest);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertNotNull($result->exception);
    }
}
