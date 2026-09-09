<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Sitebuilder\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use SandwaveIo\BaseKit\Api\Interfaces\PackagesApiInterface;
use SandwaveIo\BaseKit\Api\Interfaces\SitesApiInterface;
use SandwaveIo\BaseKit\Api\Interfaces\UserApiInterface;
use SandwaveIo\BaseKit\BaseKit;
use SandwaveIo\BaseKit\Domain\AccountHolder;
use SandwaveIo\BaseKit\Domain\Capabilities;
use SandwaveIo\BaseKit\Domain\Domain;
use SandwaveIo\BaseKit\Domain\Site;
use Tests\Factories\BasekitContextFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;
use Waterfront\Domain\Provision\Models\ProvisioningResult;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Domain\Provision\Repositories\ProvisioningResultRepository;
use Waterfront\Domain\Provision\Services\ProvisionTraceabilityService;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\BasekitUserCreateException;
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitContext;
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitSitebuilderDeployment;
use Waterfront\Domain\Provision\Sitebuilder\Models\SitebuilderDeployment;
use Waterfront\Domain\Provision\Sitebuilder\Requests\CreateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Results\SitebuilderResult;
use Waterfront\Domain\Provision\Sitebuilder\Services\CreateBasekitService;
use Waterfront\Infra\Basekit\Config\ConnectorConfig;

#[CoversClass(CreateSitebuilderRequest::class)]
#[CoversClass(ProvisionGateway::class)]
#[CoversClass(ProvisionTraceabilityService::class)]
#[CoversClass(ProvisioningResultRepository::class)]
#[CoversClass(ProvisioningRequestRepository::class)]
#[CoversClass(CreateBasekitService::class)]
class CreateSitebuilderIntegrationTest extends IntegrationTestCase
{
    public const int BRAND_REFERENCE = 1234;

    private ProvisionGateway $gateway;

    private UserApiInterface&MockObject $userApi;

    private SitesApiInterface&MockObject $sitesApi;

    private PackagesApiInterface&MockObject $packagesApi;

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
        $this->userApi = $baseKit->userApi = $this->createMock(UserApiInterface::class);
        $this->sitesApi = $baseKit->sitesApi = $this->createMock(SitesApiInterface::class);
        $this->packagesApi = $baseKit->packageApi = $this->createMock(PackagesApiInterface::class);
        $this->app->bind(BaseKit::class, fn () => $baseKit);

        $this->gateway = self::resolve(ProvisionGateway::class);
        $this->requestRepository = self::resolve(ProvisioningRequestRepository::class);
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

        $accountHolder = $this->getAccountHolderDto();
        $accountHolder->ref = 234;
        $this->userApi->expects($this->once())
            ->method('create')
            ->with(
                self::BRAND_REFERENCE,
                $firstname,
                $lastname,
                self::callback(fn ($username): bool => true),
                self::callback(fn ($password): bool => true),
                $email,
                self::callback(fn ($languageCode): bool => true),
            )
            ->willReturn($accountHolder);

        $index = 0;
        $this->packagesApi->expects($this->exactly(count($packages)))
            ->method('addUserPackage')
            ->with(
                $accountHolder->ref,
                self::callback(function ($package) use ($packages, &$index): bool {
                    return $package === $packages[$index++];
                }),
                $contractPeriod
            );

        $siteDto = $this->getSiteDto();
        $siteDto->domains = [new Domain(777, $domain)];

        $this->sitesApi->expects($this->once())
            ->method('create')
            ->with($accountHolder->ref, self::BRAND_REFERENCE, $domain)
            ->willReturn($siteDto);

        self::assertDatabaseCount(ProvisioningResult::class, 0);
        self::assertDatabaseCount(ProvisioningRequest::class, 0);
        self::assertDatabaseCount(BasekitContext::class, 0);
        self::assertDatabaseCount(SitebuilderDeployment::class, 0);
        self::assertDatabaseCount(BasekitSitebuilderDeployment::class, 0);

        $result = $this->gateway->request($request);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);
        self::assertDatabaseCount(BasekitContext::class, 1);
        self::assertDatabaseCount(SitebuilderDeployment::class, 1);
        self::assertDatabaseCount(BasekitSitebuilderDeployment::class, 1);

        self::assertInstanceOf(SitebuilderResult::class, $result);
        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);

        self::assertNull($result->exception);

        $savedRequest = $this->requestRepository->findById($request->requestId);
        self::assertNotNull($savedRequest);
        self::assertNotNull($savedRequest->context_uuid);
        self::assertTrue($savedRequest->context_uuid->equals($context));
        self::assertSame(ProvisionType::SITEBUILDER, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::CREATE_SITEBUILDER, $savedRequest->request_name);
        self::assertSame(
            $savedRequest->request_data,
            sprintf(
                '{"email": "%s", "domain": "%s", "lastname": "%s", "packages": %s, "firstname": "%s", "contractPeriod": %d}',
                $email,
                $domain,
                $lastname,
                str_replace(',', ', ', json_encode($packages, JSON_THROW_ON_ERROR)),
                $firstname,
                $contractPeriod
            )
        );
    }

    #[Test]
    public function createSitebuilderRequestUserAlreadyExists(): void
    {
        $basekitContext = BasekitContextFactory::new()->createOne();
        $request = new CreateSitebuilderRequest(
            domain: $domain = 'example.com',
            packages: $packages = [1337, 7331],
            firstname: $firstname = 'John',
            lastname: $lastname = 'Doe',
            email: $email = 'info@yourhosting.nl',
            contractPeriod: $contractPeriod = 12,
            context: $basekitContext->context_uuid,
        );

        $this->userApi->expects($this->never())
            ->method('create');

        $accountHolder = $this->getAccountHolderDto();
        $accountHolder->ref = $basekitContext->user_ref;

        $index = 0;
        $this->packagesApi->expects($this->exactly(count($packages)))
            ->method('addUserPackage')
            ->with(
                $accountHolder->ref,
                self::callback(function ($package) use ($packages, &$index): bool {
                    return $package === $packages[$index++];
                }),
                $contractPeriod
            );

        $siteDto = $this->getSiteDto();
        $siteDto->domains = [new Domain(777, $domain)];

        $this->sitesApi->expects($this->once())
            ->method('create')
            ->with($accountHolder->ref, self::BRAND_REFERENCE, $domain)
            ->willReturn($siteDto);

        self::assertDatabaseCount(ProvisioningResult::class, 0);
        self::assertDatabaseCount(ProvisioningRequest::class, 0);
        self::assertDatabaseCount(BasekitContext::class, 1);
        self::assertDatabaseCount(SitebuilderDeployment::class, 0);
        self::assertDatabaseCount(BasekitSitebuilderDeployment::class, 0);

        $result = $this->gateway->request($request);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);
        self::assertDatabaseCount(BasekitContext::class, 1);
        self::assertDatabaseCount(SitebuilderDeployment::class, 1);
        self::assertDatabaseCount(BasekitSitebuilderDeployment::class, 1);

        self::assertInstanceOf(SitebuilderResult::class, $result);
        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);

        self::assertNull($result->exception);

        $savedRequest = $this->requestRepository->findById($request->requestId);
        self::assertNotNull($savedRequest);
        self::assertNotNull($savedRequest->context_uuid);
        self::assertTrue($savedRequest->context_uuid->equals($basekitContext->context_uuid));
        self::assertSame(ProvisionType::SITEBUILDER, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::CREATE_SITEBUILDER, $savedRequest->request_name);
        self::assertSame(
            $savedRequest->request_data,
            sprintf(
                '{"email": "%s", "domain": "%s", "lastname": "%s", "packages": %s, "firstname": "%s", "contractPeriod": %d}',
                $email,
                $domain,
                $lastname,
                str_replace(',', ', ', json_encode($packages, JSON_THROW_ON_ERROR)),
                $firstname,
                $contractPeriod
            )
        );
    }

    #[Test]
    public function createSitebuilderRequestThrowsSitebuilderException(): void
    {
        $request = new CreateSitebuilderRequest(
            domain: 'example.com',
            packages: [1337, 7331],
            firstname: 'John',
            lastname: 'Doe',
            email: 'info@yourhosting.nl',
            contractPeriod: 12,
            context: $context = Uuid::uuid4(),
        );

        $this->userApi->expects($this->once())
            ->method('create')
            ->willThrowException(new BasekitUserCreateException($context));

        $this->packagesApi->expects($this->never())
            ->method('addUserPackage');

        $this->sitesApi->expects($this->never())
            ->method('create');

        self::assertDatabaseCount(ProvisioningResult::class, 0);
        self::assertDatabaseCount(ProvisioningRequest::class, 0);
        self::assertDatabaseCount(BasekitContext::class, 0);
        self::assertDatabaseCount(SitebuilderDeployment::class, 0);
        self::assertDatabaseCount(BasekitSitebuilderDeployment::class, 0);

        $result = $this->gateway->request($request);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);
        self::assertDatabaseCount(BasekitContext::class, 0);
        self::assertDatabaseCount(SitebuilderDeployment::class, 0);
        self::assertDatabaseCount(BasekitSitebuilderDeployment::class, 0);

        self::assertInstanceOf(SitebuilderResult::class, $result);
        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);

        self::assertNotNull($result->exception);
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
