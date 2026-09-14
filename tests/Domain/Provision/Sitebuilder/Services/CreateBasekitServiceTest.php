<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Sitebuilder\Services;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use SandwaveIo\BaseKit\Api\Interfaces\PackagesApiInterface;
use SandwaveIo\BaseKit\Api\Interfaces\SitesApiInterface;
use SandwaveIo\BaseKit\Api\Interfaces\UserApiInterface;
use SandwaveIo\BaseKit\BaseKit;
use SandwaveIo\BaseKit\Domain\AccountHolder;
use SandwaveIo\BaseKit\Domain\Capabilities;
use SandwaveIo\BaseKit\Domain\Domain;
use SandwaveIo\BaseKit\Domain\Site;
use SandwaveIo\BaseKit\Exceptions\BaseKitClientException;
use Tests\TestCase;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitContext;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\BasekitContextRepository;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\BasekitSitebuilderDeploymentRepository;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\SitebuilderDeploymentRepository;
use Waterfront\Domain\Provision\Sitebuilder\Requests\CreateBasekitDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\CreateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Services\CreateBasekitService;
use Waterfront\Infra\Basekit\Config\ConnectorConfig;
use Waterfront\Infra\PasswordGenerator\AlphaNumericGenerator;
use Waterfront\Infra\PasswordGenerator\DefaultGenerator;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(CreateBasekitService::class)]
#[AllowMockObjectsWithoutExpectations]
class CreateBasekitServiceTest extends TestCase
{
    private UserApiInterface&MockObject $userApi;

    private SitesApiInterface&MockObject $sitesApi;

    private PackagesApiInterface&MockObject $packagesApi;

    private LoggerInterface&MockObject $logger;

    private BasekitContextRepository&MockObject $baseKitContextRepository;

    private CreateBasekitService $createBasekitService;

    protected function setUp(): void
    {
        parent::setUp();

        $baseKit = new BaseKit('username', 'password');
        $this->userApi = $baseKit->userApi = $this->createMock(UserApiInterface::class);
        $this->sitesApi = $baseKit->sitesApi = $this->createMock(SitesApiInterface::class);
        $this->packagesApi = $baseKit->packageApi = $this->createMock(PackagesApiInterface::class);
        $this->baseKitContextRepository = self::createMock(BaseKitContextRepository::class);
        $sitebuilderDeploymentRepository = self::createMock(SitebuilderDeploymentRepository::class);
        $basekitSitebuilderDeploymentRepository = self::createMock(BasekitSitebuilderDeploymentRepository::class);

        $config = new ConnectorConfig(
            baseUrl: 'https://',
            ssoUrl: 'https://',
            username: 'username',
            password: 'password',
            brandReference: 1234,
        );
        $this->createBasekitService = new CreateBasekitService(
            baseKitClient: $baseKit,
            config: $config,
            logger: $this->logger = self::createMock(LoggerInterface::class),
            baseKitContextRepository: $this->baseKitContextRepository,
            sitebuilderDeploymentRepository: $sitebuilderDeploymentRepository,
            basekitSitebuilderDeploymentRepository: $basekitSitebuilderDeploymentRepository,
            // We 'make' these dependencies because they have final methods we can't mock
            usernameGenerator: $this->app->make(AlphaNumericGenerator::class),
            passwordGenerator: $this->app->make(DefaultGenerator::class),
        );
    }

    #[Test]
    public function createSitebuilderRequestSuccessful(): void
    {
        $request = new CreateSitebuilderRequest(
            domain: $domain = 'example.com',
            packages: $packages = [1337, 7331],
            firstname: $firstname = 'John',
            lastname: $lastname = 'Doe',
            email: $email = 'info@yourhosting.nl',
            contractPeriod: $contractPeriod = 12,
            context: $context = Uuid::uuid4(),
        );

        $request->requestId = 1234;

        $accountHolder = $this->getAccountHolderDto();
        $accountHolder->ref = 234;

        $this->baseKitContextRepository
            ->expects($this->once())
            ->method('findByContext')
            ->with($context)
            ->willReturn(null);

        $this->userApi
            ->expects($this->once())
            ->method('create')
            ->with(
                1234,
                $firstname,
                $lastname,
                self::callback(fn ($username): bool => true),
                self::callback(fn ($password): bool => true),
                $email,
                self::callback(fn ($languageCode): bool => true),
            )
            ->willReturn($accountHolder);

        $basekitContext = new BasekitContext();
        $basekitContext->user_ref = $accountHolder->ref;
        $this->baseKitContextRepository->expects($this->once())->method('create')->willReturn($basekitContext);

        $index = 0;
        $this->packagesApi
            ->expects($this->exactly(count($packages)))
            ->method('addUserPackage')
            ->with(
                $accountHolder->ref,
                self::callback(function ($package) use ($packages, &$index): bool {
                    return $package === $packages[$index++];
                }),
                $contractPeriod,
            );

        $siteDto = $this->getSiteDto();
        $siteDto->domains = [new Domain(777, $domain)];

        $this->sitesApi
            ->expects($this->once())
            ->method('create')
            ->with($accountHolder->ref, 1234, $domain)
            ->willReturn($siteDto);

        $result = $this->createBasekitService->create($request);
        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertSame($result->provisionData, $request);
    }

    #[Test]
    public function createSitebuilderRequestContextAlreadyExists(): void
    {
        $request = new CreateSitebuilderRequest(
            domain: $domain = 'example.com',
            packages: $packages = [1337, 7331],
            firstname: 'John',
            lastname: 'Doe',
            email: 'info@yourhosting.nl',
            contractPeriod: $contractPeriod = 12,
            context: $context = Uuid::uuid4(),
        );

        $request->requestId = 1234;

        $basekitContext = new BasekitContext();
        $basekitContext->user_ref = 234;

        $accountHolder = $this->getAccountHolderDto();
        $accountHolder->ref = $basekitContext->user_ref;

        $this->baseKitContextRepository
            ->expects($this->once())
            ->method('findByContext')
            ->with($context)
            ->willReturn($basekitContext);

        $this->userApi->expects($this->never())->method('create');

        $basekitContext = new BasekitContext();
        $basekitContext->user_ref = $accountHolder->ref;
        $this->baseKitContextRepository->expects($this->never())->method('create');

        $index = 0;
        $this->packagesApi
            ->expects($this->exactly(count($packages)))
            ->method('addUserPackage')
            ->with(
                $accountHolder->ref,
                self::callback(function ($package) use ($packages, &$index): bool {
                    return $package === $packages[$index++];
                }),
                $contractPeriod,
            );

        $siteDto = $this->getSiteDto();
        $siteDto->domains = [new Domain(777, $domain)];

        $this->sitesApi
            ->expects($this->once())
            ->method('create')
            ->with($accountHolder->ref, 1234, $domain)
            ->willReturn($siteDto);

        $result = $this->createBasekitService->create($request);
        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertSame($result->provisionData, $request);
    }

    #[Test]
    public function failsToCreateUser(): void
    {
        $request = new CreateSitebuilderRequest(
            domain: $domain = 'example.com',
            packages: [1337],
            firstname: 'John',
            lastname: 'Doe',
            email: 'info@yourhosting.nl',
            contractPeriod: 12,
            context: $context = Uuid::uuid4(),
        );

        $request->requestId = 1234;
        $request->provider = ProvisionProvider::BASEKIT;

        $this->baseKitContextRepository->expects($this->once())->method('findByContext')->willReturn(null);

        $this->userApi
            ->expects($this->once())
            ->method('create')
            ->willThrowException($exception = self::createMock(BaseKitClientException::class));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                sprintf(
                    'Failed to create sitebuilder user for context %s',
                    $context->toString(),
                ),
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
                    LoggingContextKeys::PROVISIONING_REQUEST_ID => 1234,
                    LoggingContextKeys::PROVISIONING_CONTEXT => $context,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                ],
            );

        $result = $this->createBasekitService->create($request);
        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
    }

    #[Test]
    public function failsToAddUserPackage(): void
    {
        $request = new CreateSitebuilderRequest(
            domain: 'example.com',
            packages: [1337],
            firstname: 'John',
            lastname: 'Doe',
            email: 'info@yourhosting.nl',
            contractPeriod: $contractPeriod = 12,
            context: $context = Uuid::uuid4(),
        );

        $request->requestId = 1234;
        $request->provider = ProvisionProvider::BASEKIT;

        $basekitContext = new BasekitContext();
        $basekitContext->user_ref = 234;

        $accountHolder = $this->getAccountHolderDto();
        $accountHolder->ref = $basekitContext->user_ref;

        $this->baseKitContextRepository->expects($this->once())->method('findByContext')->willReturn($basekitContext);

        $this->packagesApi
            ->expects($this->once())
            ->method('addUserPackage')
            ->willThrowException($exception = self::createMock(BaseKitClientException::class));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                sprintf(
                    'Failed to add package %d for user %d',
                    1337,
                    $accountHolder->ref,
                ),
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
                    LoggingContextKeys::PROVISIONING_REQUEST_ID => 1234,
                    LoggingContextKeys::PROVISIONING_CONTEXT => $context,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'basekit_package_ref' => 1337,
                        'basekit_user_ref' => $accountHolder->ref,
                        'basekit_subscription_period' => $contractPeriod,
                    ],
                ],
            );

        $result = $this->createBasekitService->create($request);
        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
    }

    #[Test]
    public function failsToCreateSite(): void
    {
        $request = new CreateSitebuilderRequest(
            domain: $domain = 'example.com',
            packages: [1337],
            firstname: 'John',
            lastname: 'Doe',
            email: 'info@yourhosting.nl',
            contractPeriod: 12,
            context: $context = Uuid::uuid4(),
        );

        $request->requestId = 1234;
        $request->provider = ProvisionProvider::BASEKIT;

        $basekitContext = new BasekitContext();
        $basekitContext->user_ref = 234;

        $accountHolder = $this->getAccountHolderDto();
        $accountHolder->ref = $basekitContext->user_ref;

        $this->baseKitContextRepository->expects($this->once())->method('findByContext')->willReturn($basekitContext);

        $this->sitesApi
            ->expects($this->once())
            ->method('create')
            ->willThrowException($exception = self::createMock(BaseKitClientException::class));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                sprintf(
                    'Failed to create site "%s" for user %d',
                    $domain,
                    $accountHolder->ref,
                ),
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
                    LoggingContextKeys::PROVISIONING_REQUEST_ID => 1234,
                    LoggingContextKeys::PROVISIONING_CONTEXT => $context,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::META => [
                        'basekit_user_ref' => $accountHolder->ref,
                    ],
                ],
            );

        $result = $this->createBasekitService->create($request);
        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
    }

    #[Test]
    public function createBasekitDeploymentsFromMigrationRequestSuccessful(): void
    {
        $request = new CreateBasekitDeploymentsFromMigrationRequest(
            domain: $domain = 'example.com',
            userRef: 1234,
            siteRef: 5678,
            context: $context = Uuid::uuid4(),
        );

        $request->requestId = 1234;

        $this->baseKitContextRepository
            ->expects($this->once())
            ->method('findWithTrashedByContext')
            ->with($context)
            ->willReturn(null);

        $basekitContext = new BasekitContext();
        $basekitContext->user_ref = $request->userRef;
        $this->baseKitContextRepository->expects($this->once())->method('create')->willReturn($basekitContext);

        $result = $this->createBasekitService->createFromMigration($request);
        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertSame($result->provisionData, $request);
    }

    #[Test]
    public function createBasekitDeploymentsFromMigrationRequestContextAlreadyExists(): void
    {
        $request = new CreateBasekitDeploymentsFromMigrationRequest(
            domain: $domain = 'example.com',
            userRef: 234,
            siteRef: 5678,
            context: $context = Uuid::uuid4(),
        );

        $request->requestId = 1234;

        $basekitContext = new BasekitContext();
        $basekitContext->user_ref = $request->userRef;

        $this->baseKitContextRepository
            ->expects($this->once())
            ->method('findWithTrashedByContext')
            ->with($context)
            ->willReturn($basekitContext);

        $this->baseKitContextRepository->expects($this->never())->method('create');

        $result = $this->createBasekitService->createFromMigration($request);
        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertSame($result->provisionData, $request);
    }

    private function getAccountHolderDto(): AccountHolder
    {
        return new AccountHolder(
            0,
            0,
            '',
            '',
            '',
            '',
            0,
            false,
            '',
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
    }

    private function getSiteDto(): Site
    {
        return new Site(
            0,
            [],
            null,
            null,
            new Domain(0, ''),
            null,
            0,
            0,
            true,
            null,
            null,
            true,
            null,
        );
    }
}
