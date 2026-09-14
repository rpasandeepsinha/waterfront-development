<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Sitebuilder\Services;

use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use SandwaveIo\BaseKit\Api\Interfaces\SslApiInterface;
use SandwaveIo\BaseKit\BaseKit;
use SandwaveIo\BaseKit\Exceptions\BaseKitRequestException;
use Tests\Factories\BasekitSitebuilderDeploymentFactory;
use Tests\Factories\SitebuilderContextBasekitFactory;
use Tests\Factories\SitebuilderDeploymentFactory;
use Tests\TestCase;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Exceptions\DeploymentNotFoundException;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\BasekitAddSslException;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\SitebuilderDeploymentRepository;
use Waterfront\Domain\Provision\Sitebuilder\Requests\AddSslSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Services\BasekitProvisionService;
use Waterfront\Domain\Provision\Sitebuilder\Services\CreateBasekitService;
use Waterfront\Domain\Provision\Sitebuilder\Services\DeleteBasekitService;
use Waterfront\Domain\Provision\Sitebuilder\Services\UpdateBasekitService;
use Waterfront\Infra\Basekit\Config\ConnectorConfig;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(BasekitProvisionService::class)]
class SslBasekitProvisionServiceTest extends TestCase
{
    private const string TEST_DOMAIN = 'sitebuilder-basekit.nl';
    private const string TEST_KEY = 'private-key';
    private const string TEST_CERT = 'main-cert';

    private SitebuilderDeploymentRepository&MockInterface $mockSitebuilderRepository;

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
        self::mock(CreateBasekitService::class);

        $this->mockSitebuilderRepository = self::mock(SitebuilderDeploymentRepository::class);
        $this->mockLogger = self::mock(LoggerInterface::class);

        BasekitSitebuilderDeploymentFactory::dontExpandRelationshipsByDefault();
        SitebuilderDeploymentFactory::dontExpandRelationshipsByDefault();
    }

    #[Test]
    public function addSsl(): void
    {
        $tag = Uuid::uuid4();

        $addSitebuilder = new AddSslSitebuilderRequest(
            tagUuid: $tag,
            context: Uuid::uuid4(),
            privateKey: self::TEST_KEY,
            mainCertificate: self::TEST_CERT,
        );

        $addSitebuilder->requestId = 1234;
        $addSitebuilder->provider = ProvisionProvider::BASEKIT;

        $basekitContext = SitebuilderContextBasekitFactory::new()->makeOne();
        $sitebuilderDeployment = SitebuilderDeploymentFactory::new()->state(['domain' => self::TEST_DOMAIN])->makeOne();
        $basekitSitebuilderDeployment = BasekitSitebuilderDeploymentFactory::new()->makeOne();

        $sitebuilderDeployment->setRelation('basekitDeployment', $basekitSitebuilderDeployment);
        $sitebuilderDeployment->setRelation('basekitContext', $basekitContext);

        $this->mockSitebuilderRepository->expects('findByTag')->once()->with($tag)->andReturn($sitebuilderDeployment);

        $mockSslApi = self::mock(SslApiInterface::class);
        $this->mockBasekitClient->sslApi = $mockSslApi;

        $mockSslApi
            ->expects('addSsl')
            ->once()
            ->with(
                $sitebuilderDeployment->domain,
                self::TEST_KEY,
                self::TEST_CERT,
            )
            ->andReturnNull();

        $service = new BasekitProvisionService(
            createBasekitService: self::createStub(CreateBasekitService::class),
            basekitClient: $this->mockBasekitClient,
            basekitConfig: $this->basekitConfig,
            sitebuilderDeploymentRepository: $this->mockSitebuilderRepository,
            logger: $this->mockLogger,
            updateBasekitService: self::createStub(UpdateBasekitService::class),
            deleteBasekitService: self::createStub(DeleteBasekitService::class),
        );

        $this->mockLogger
            ->expects('debug')
            ->once()
            ->with('Adding SSL to Basekit sitebuilder deployment', [
                LoggingContextKeys::PROVISIONING_CONTEXT => $addSitebuilder->context,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
                LoggingContextKeys::PROVISIONING_REQUEST_ID => $addSitebuilder->requestId,
                LoggingContextKeys::DOMAIN_NAME => self::TEST_DOMAIN,
            ]);

        $result = $service->addSsl($addSitebuilder);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    #[Test]
    public function addSslNeedsDeployment(): void
    {
        $tag = Uuid::uuid4();

        $addSitebuilder = new AddSslSitebuilderRequest(
            tagUuid: $tag,
            context: Uuid::uuid4(),
            privateKey: self::TEST_KEY,
            mainCertificate: self::TEST_CERT,
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

        $result = $service->addSsl($addSitebuilder);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertNotNull($result->exception);
        self::assertInstanceOf(DeploymentNotFoundException::class, $result->exception);
        self::assertSame(
            sprintf('No sitebuilder deployment found for the given tag [%s].', $tag),
            $result->exception->getMessage(),
        );
    }

    #[Test]
    public function baseKitFailedResult(): void
    {
        $tag = Uuid::uuid4();

        $addSitebuilder = new AddSslSitebuilderRequest(
            tagUuid: $tag,
            context: Uuid::uuid4(),
            privateKey: self::TEST_KEY,
            mainCertificate: self::TEST_CERT,
        );
        $addSitebuilder->requestId = 1234;
        $addSitebuilder->provider = ProvisionProvider::BASEKIT;

        $basekitContext = SitebuilderContextBasekitFactory::new()->makeOne();
        $basekitSitebuilderDeployment = BasekitSitebuilderDeploymentFactory::new()->makeOne();
        $sitebuilderDeployment = SitebuilderDeploymentFactory::new()->state(['domain' => self::TEST_DOMAIN])->makeOne();

        $sitebuilderDeployment->setRelation('basekitDeployment', $basekitSitebuilderDeployment);
        $sitebuilderDeployment->setRelation('basekitContext', $basekitContext);

        $this->mockSitebuilderRepository->expects('findByTag')->once()->with($tag)->andReturn($sitebuilderDeployment);

        $mockSslApi = self::mock(SslApiInterface::class);
        $this->mockBasekitClient->sslApi = $mockSslApi;

        $basekitException = new BaseKitRequestException('error', 500);
        $mockSslApi
            ->expects('addSsl')
            ->once()
            ->with(
                $sitebuilderDeployment->domain,
                self::TEST_KEY,
                self::TEST_CERT,
            )
            ->andThrows($basekitException);

        $this->mockLogger
            ->expects('debug')
            ->once()
            ->with('Adding SSL to Basekit sitebuilder deployment', [
                LoggingContextKeys::PROVISIONING_CONTEXT => $addSitebuilder->context,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
                LoggingContextKeys::PROVISIONING_REQUEST_ID => $addSitebuilder->requestId,
                LoggingContextKeys::DOMAIN_NAME => self::TEST_DOMAIN,
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

        $this->mockLogger
            ->expects('warning')
            ->once()
            ->with('Adding SSL to Basekit sitebuilder deployment failed', [
                LoggingContextKeys::PROVISIONING_CONTEXT => $addSitebuilder->context,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
                LoggingContextKeys::PROVISIONING_REQUEST_ID => $addSitebuilder->requestId,
                LoggingContextKeys::DOMAIN_NAME => self::TEST_DOMAIN,
                LoggingContextKeys::EXCEPTION => $basekitException,
            ]);

        $result = $service->addSsl($addSitebuilder);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(BasekitAddSslException::class, $result->exception);
        self::assertSame($basekitException, $result->exception->getPrevious());
    }
}
