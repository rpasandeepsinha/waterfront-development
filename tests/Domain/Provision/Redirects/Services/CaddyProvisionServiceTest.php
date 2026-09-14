<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Redirects\Services;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\SaloonException;
use Tests\Factories\CaddyContextFactory;
use Tests\Factories\CaddyRedirectDeploymentFactory;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\Factories\RedirectDeploymentFactory;
use Tests\TestCase;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Exceptions\DeploymentNotFoundException;
use Waterfront\Domain\Provision\Redirects\DTO\Redirect;
use Waterfront\Domain\Provision\Redirects\DTO\RedirectSourceMatchers;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Domain\Provision\Redirects\Models\RedirectDeployment;
use Waterfront\Domain\Provision\Redirects\Repositories\CaddyRedirectDeploymentRepository;
use Waterfront\Domain\Provision\Redirects\Repositories\RedirectContextRepository;
use Waterfront\Domain\Provision\Redirects\Repositories\RedirectDeploymentRepository;
use Waterfront\Domain\Provision\Redirects\Requests\CreateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\DeleteRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\GetRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\ListRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Requests\SuspendRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\TerminateRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Requests\UnsuspendRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\UpdateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Results\GetRedirectResult;
use Waterfront\Domain\Provision\Redirects\Services\CaddyProvisionClientMapper;
use Waterfront\Domain\Provision\Redirects\Services\CaddyProvisionService;
use Waterfront\Infra\CaddyClient\CaddyClient;
use Waterfront\Infra\CaddyClient\DTO\RedirectRoute;
use Waterfront\Infra\CaddyClient\Enums\RedirectType as CaddyRedirectType;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(CaddyProvisionService::class)]
class CaddyProvisionServiceTest extends TestCase
{
    private CaddyClient&MockObject $caddyClient;

    public function setUp(): void
    {
        parent::setUp();

        $this->caddyClient = self::createMock(CaddyClient::class);

        RedirectDeploymentFactory::dontExpandRelationshipsByDefault();
    }

    #[Test]
    public function createRedirectSuccessNoParameter(): void
    {
        $domain = 'yourhosting.com';
        $destination = 'versio.com';
        $context = Uuid::uuid4();
        $caddyId = 'yh12345';

        $provisioningRequest = new ProvisioningRequestFactory()
            ->redirect()
            ->makeOne([
                'request_name' => ProvisionRequestName::CREATE_REDIRECT,
                'id' => 1,
            ]);

        $createRedirectRequest = new CreateRedirectRequest(
            domain: $domain,
            destinationUrl: $destination,
            redirectType: RedirectType::PERMANENT,
            context: $context,
        );
        $createRedirectRequest->requestId = $provisioningRequest->id;

        $caddyContext = CaddyContextFactory::new()->makeOne([
            'context_uuid' => $context,
            'domain' => $domain,
        ]);

        $redirectDeployment = RedirectDeploymentFactory::new()->makeOne([
            'source' => $domain,
            'destination' => $destination,
        ]);

        $caddyRedirectDeployment = CaddyRedirectDeploymentFactory::new()->makeOne();

        $redirectContextRepository = self::createMock(RedirectContextRepository::class);
        $redirectContextRepository
            ->expects(self::once())
            ->method('findOrCreate')
            ->with($context, $domain)
            ->willReturn($caddyContext);

        $this->caddyClient
            ->expects(self::once())
            ->method('createRedirect')
            ->with(
                $domain,
                $destination,
                CaddyRedirectType::MOVED_PERMANENTLY,
                null,
                null,
            )
            ->willReturn($caddyId);

        $redirectDeploymentRepository = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepository
            ->expects(self::once())
            ->method('create')
            ->with(
                $provisioningRequest->id,
                $domain,
                $destination,
                RedirectType::PERMANENT,
                $context,
            )
            ->willReturn($redirectDeployment);

        $caddyRedirectDeploymentRepository = self::createMock(CaddyRedirectDeploymentRepository::class);
        $caddyRedirectDeploymentRepository
            ->expects(self::once())
            ->method('create')
            ->with($redirectDeployment, $caddyId)
            ->willReturn($caddyRedirectDeployment);

        $caddyProvisionClientMapper = self::createMock(CaddyProvisionClientMapper::class);
        $caddyProvisionClientMapper
            ->expects(self::once())
            ->method('getCaddyRedirectType')
            ->with(RedirectType::PERMANENT)
            ->willReturn(CaddyRedirectType::MOVED_PERMANENTLY);

        $caddyProvisionClientMapper
            ->expects(self::once())
            ->method('parseSourceMatchers')
            ->with($createRedirectRequest->domain)
            ->willReturn(new RedirectSourceMatchers(
                host: $domain,
            ));

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: self::createStub(LoggerInterface::class),
            redirectDeploymentRepository: $redirectDeploymentRepository,
            redirectContextRepository: $redirectContextRepository,
            caddyRedirectDeploymentRepository: $caddyRedirectDeploymentRepository,
            caddyProvisionClientMapper: $caddyProvisionClientMapper,
        );

        $result = $service->createRedirect($createRedirectRequest);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    #[Test]
    public function createRedirectSuccessPath(): void
    {
        $host = 'yourhosting.com';
        $domain = $host . '/lol/';
        $destination = 'versio.com/product?utm_source=newsletter&utm_medium=email';
        $context = Uuid::uuid4();
        $caddyId = 'yh12345';

        $provisioningRequest = new ProvisioningRequestFactory()
            ->redirect()
            ->makeOne([
                'request_name' => ProvisionRequestName::CREATE_REDIRECT,
                'id' => 1,
            ]);

        $createRedirectRequest = new CreateRedirectRequest(
            domain: $domain,
            destinationUrl: $destination,
            redirectType: RedirectType::PERMANENT,
            context: $context,
        );
        $createRedirectRequest->requestId = $provisioningRequest->id;

        $caddyContext = CaddyContextFactory::new()->makeOne([
            'context_uuid' => $context,
            'domain' => $domain,
        ]);

        $redirectDeployment = RedirectDeploymentFactory::new()->makeOne([
            'source' => $domain,
            'destination' => $destination,
        ]);

        $caddyRedirectDeployment = CaddyRedirectDeploymentFactory::new()->makeOne();

        $redirectContextRepository = self::createMock(RedirectContextRepository::class);
        $redirectContextRepository
            ->expects(self::once())
            ->method('findOrCreate')
            ->with($context, $host)
            ->willReturn($caddyContext);

        $this->caddyClient
            ->expects(self::once())
            ->method('createRedirect')
            ->with(
                $host,
                $destination,
                CaddyRedirectType::MOVED_PERMANENTLY,
                ['/lol/'],
                null,
            )
            ->willReturn($caddyId);

        $redirectDeploymentRepository = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepository
            ->expects(self::once())
            ->method('create')
            ->with(
                $provisioningRequest->id,
                $domain,
                $destination,
                RedirectType::PERMANENT,
                $context,
            )
            ->willReturn($redirectDeployment);

        $caddyRedirectDeploymentRepository = self::createMock(CaddyRedirectDeploymentRepository::class);
        $caddyRedirectDeploymentRepository
            ->expects(self::once())
            ->method('create')
            ->with($redirectDeployment, $caddyId)
            ->willReturn($caddyRedirectDeployment);

        $caddyProvisionClientMapper = self::createMock(CaddyProvisionClientMapper::class);
        $caddyProvisionClientMapper
            ->expects(self::once())
            ->method('getCaddyRedirectType')
            ->with(RedirectType::PERMANENT)
            ->willReturn(CaddyRedirectType::MOVED_PERMANENTLY);

        $caddyProvisionClientMapper
            ->expects(self::once())
            ->method('parseSourceMatchers')
            ->with($createRedirectRequest->domain)
            ->willReturn(new RedirectSourceMatchers(
                host: $host,
                paths: ['/lol/'],
            ));

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: self::createStub(LoggerInterface::class),
            redirectDeploymentRepository: $redirectDeploymentRepository,
            redirectContextRepository: $redirectContextRepository,
            caddyRedirectDeploymentRepository: $caddyRedirectDeploymentRepository,
            caddyProvisionClientMapper: $caddyProvisionClientMapper,
        );

        $result = $service->createRedirect($createRedirectRequest);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    public function testCreateRedirectSuccessPathAndQuery(): void
    {
        $host = 'yourhosting.com';
        $domain = $host . '/lol/?x=1&x=2';
        $destination = 'versio.com/product?utm_source=newsletter&utm_medium=email';
        $context = Uuid::uuid4();
        $caddyId = 'yh12345';

        $provisioningRequest = new ProvisioningRequestFactory()
            ->redirect()
            ->makeOne([
                'request_name' => ProvisionRequestName::CREATE_REDIRECT,
                'id' => 1,
            ]);

        $createRedirectRequest = new CreateRedirectRequest(
            domain: $domain,
            destinationUrl: $destination,
            redirectType: RedirectType::PERMANENT,
            context: $context,
        );
        $createRedirectRequest->requestId = $provisioningRequest->id;

        $caddyContext = CaddyContextFactory::new()->makeOne([
            'context_uuid' => $context,
            'domain' => $domain,
        ]);

        $redirectDeployment = RedirectDeploymentFactory::new()->makeOne([
            'source' => $domain,
            'destination' => $destination,
        ]);

        $caddyRedirectDeployment = CaddyRedirectDeploymentFactory::new()->makeOne();

        $redirectContextRepository = self::createMock(RedirectContextRepository::class);
        $redirectContextRepository
            ->expects(self::once())
            ->method('findOrCreate')
            ->with($context, $host)
            ->willReturn($caddyContext);

        $this->caddyClient
            ->expects(self::once())
            ->method('createRedirect')
            ->with(
                $host,
                $destination,
                CaddyRedirectType::MOVED_PERMANENTLY,
                ['/lol/'],
                ['x' => ['1', '2']],
            )
            ->willReturn($caddyId);

        $redirectDeploymentRepository = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepository
            ->expects(self::once())
            ->method('create')
            ->with(
                $provisioningRequest->id,
                $domain,
                $destination,
                RedirectType::PERMANENT,
                $context,
            )
            ->willReturn($redirectDeployment);

        $caddyRedirectDeploymentRepository = self::createMock(CaddyRedirectDeploymentRepository::class);
        $caddyRedirectDeploymentRepository
            ->expects(self::once())
            ->method('create')
            ->with($redirectDeployment, $caddyId)
            ->willReturn($caddyRedirectDeployment);

        $caddyProvisionClientMapper = self::createMock(CaddyProvisionClientMapper::class);
        $caddyProvisionClientMapper
            ->expects(self::once())
            ->method('getCaddyRedirectType')
            ->with(RedirectType::PERMANENT)
            ->willReturn(CaddyRedirectType::MOVED_PERMANENTLY);

        $caddyProvisionClientMapper
            ->expects(self::once())
            ->method('parseSourceMatchers')
            ->with($createRedirectRequest->domain)
            ->willReturn(new RedirectSourceMatchers(
                host: $host,
                paths: ['/lol/'],
                query: ['x' => ['1', '2']],
            ));

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: self::createStub(LoggerInterface::class),
            redirectDeploymentRepository: $redirectDeploymentRepository,
            redirectContextRepository: $redirectContextRepository,
            caddyRedirectDeploymentRepository: $caddyRedirectDeploymentRepository,
            caddyProvisionClientMapper: $caddyProvisionClientMapper,
        );

        $result = $service->createRedirect($createRedirectRequest);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    #[Test]
    public function createRedirectClientException(): void
    {
        $domain = 'yourhosting.com';
        $destination = 'versio.com';
        $context = Uuid::uuid4();

        $provisioningRequest = new ProvisioningRequestFactory()
            ->redirect()
            ->makeOne([
                'request_name' => ProvisionRequestName::CREATE_REDIRECT,
                'id' => 1,
            ]);

        $createRedirectRequest = new CreateRedirectRequest(
            domain: $domain,
            destinationUrl: $destination,
            redirectType: RedirectType::PERMANENT,
            context: $context,
        );
        $createRedirectRequest->requestId = $provisioningRequest->id;

        $caddyContext = CaddyContextFactory::new()->makeOne([
            'context_uuid' => $context,
            'domain' => $domain,
        ]);

        $redirectContextRepository = self::createMock(RedirectContextRepository::class);
        $redirectContextRepository
            ->expects(self::once())
            ->method('findOrCreate')
            ->with($context, $domain)
            ->willReturn($caddyContext);

        $requestException = self::createStub(RequestException::class);
        $this->caddyClient->expects(self::once())->method('createRedirect')->willThrowException($requestException);

        $redirectDeploymentRepository = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepository->expects(self::never())->method('create');

        $caddyRedirectDeploymentRepository = self::createMock(CaddyRedirectDeploymentRepository::class);
        $caddyRedirectDeploymentRepository->expects(self::never())->method('create');

        $caddyProvisionClientMapper = self::createMock(CaddyProvisionClientMapper::class);
        $caddyProvisionClientMapper
            ->expects(self::once())
            ->method('getCaddyRedirectType')
            ->with(RedirectType::PERMANENT)
            ->willReturn(CaddyRedirectType::MOVED_PERMANENTLY);

        $caddyProvisionClientMapper
            ->expects(self::once())
            ->method('parseSourceMatchers')
            ->with($createRedirectRequest->domain)
            ->willReturn(new RedirectSourceMatchers(
                host: $domain,
            ));

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: self::createStub(LoggerInterface::class),
            redirectDeploymentRepository: $redirectDeploymentRepository,
            redirectContextRepository: $redirectContextRepository,
            caddyRedirectDeploymentRepository: $caddyRedirectDeploymentRepository,
            caddyProvisionClientMapper: $caddyProvisionClientMapper,
        );

        $result = $service->createRedirect($createRedirectRequest);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($requestException, $result->exception);
    }

    #[Test]
    public function getRedirectSuccess(): void
    {
        $caddyId = '1337';
        $domain = 'yourhosting.nl';
        $context = Uuid::uuid4();
        $request = new GetRedirectRequest($domain, $context);
        $request->requestId = 1;

        $expectedRedirect = new RedirectRoute(
            id: $caddyId,
            match: [],
            handle: [],
        );

        $expectedRedirectResult = new GetRedirectResult(
            provisionData: $request,
            provisionStatus: ProvisionStatus::SUCCESS,
            redirect: new Redirect(
                source: '',
                destination: '',
                redirectType: RedirectType::PERMANENT,
            ),
        );

        $redirecDeployment = $this->makeRedirectDeployment($domain, $context, $caddyId);

        $redirectDeploymentRepo = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepo
            ->expects(self::once())
            ->method('findBySourceAndContext')
            ->with($domain, $context)
            ->willReturn($redirecDeployment);

        $this->caddyClient->expects(self::once())->method('getRedirect')->with($caddyId)->willReturn($expectedRedirect);

        $caddyMapper = self::createMock(CaddyProvisionClientMapper::class);
        $caddyMapper
            ->expects(self::once())
            ->method('getRedirectDtoFromCaddyDto')
            ->with($expectedRedirect)
            ->willReturn($expectedRedirectResult->redirect);

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: self::createStub(LoggerInterface::class),
            redirectDeploymentRepository: $redirectDeploymentRepo,
            redirectContextRepository: self::createStub(RedirectContextRepository::class),
            caddyRedirectDeploymentRepository: self::createStub(CaddyRedirectDeploymentRepository::class),
            caddyProvisionClientMapper: $caddyMapper,
        );

        $result = $service->getRedirect($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertInstanceOf(Redirect::class, $result->redirect);
        self::assertSame($domain, $result->redirect->source);
    }

    #[Test]
    public function getRedirectThrowsSaloonException(): void
    {
        $caddyId = '1337';
        $domain = 'yourhosting.nl';
        $context = Uuid::uuid4();
        $request = new GetRedirectRequest($domain, $context);
        $request->requestId = 1;

        $redirecDeployment = $this->makeRedirectDeployment($domain, $context, $caddyId);

        $redirectDeploymentRepo = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepo
            ->expects(self::once())
            ->method('findBySourceAndContext')
            ->with($domain, $context)
            ->willReturn($redirecDeployment);

        $this->caddyClient
            ->expects(self::once())
            ->method('getRedirect')
            ->willThrowException($exception = new SaloonException('Failed to get redirect'));

        $caddyMapper = self::createMock(CaddyProvisionClientMapper::class);
        $caddyMapper->expects(self::never())->method('getRedirectDtoFromCaddyDto');

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                sprintf('Could not retrieve redirects by key: [%s]', $caddyId),
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                    LoggingContextKeys::PROVISIONING_CONTEXT => $context,
                    LoggingContextKeys::PROVISIONING_REQUEST_ID => $request->requestId,
                    LoggingContextKeys::META => [
                        'caddy_id' => $caddyId,
                    ],
                ],
            );

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: $logger,
            redirectDeploymentRepository: $redirectDeploymentRepo,
            redirectContextRepository: self::createStub(RedirectContextRepository::class),
            caddyRedirectDeploymentRepository: self::createStub(CaddyRedirectDeploymentRepository::class),
            caddyProvisionClientMapper: $caddyMapper,
        );

        $result = $service->getRedirect($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertNull($result->redirect);
        self::assertInstanceOf(SaloonException::class, $result->exception);
        self::assertSame('Failed to get redirect', $result->exception->getMessage());
    }

    #[Test]
    public function getRedirectNoDeploymentFound(): void
    {
        $domain = 'yourhosting.nl';
        $context = Uuid::uuid4();
        $request = new GetRedirectRequest($domain, $context);

        $redirectDeploymentRepo = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepo
            ->expects(self::once())
            ->method('findBySourceAndContext')
            ->with($domain, $context)
            ->willReturn(null);

        $this->caddyClient->expects(self::never())->method('getRedirect');

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: self::createStub(LoggerInterface::class),
            redirectDeploymentRepository: $redirectDeploymentRepo,
            redirectContextRepository: self::createStub(RedirectContextRepository::class),
            caddyRedirectDeploymentRepository: self::createStub(CaddyRedirectDeploymentRepository::class),
            caddyProvisionClientMapper: self::createStub(CaddyProvisionClientMapper::class),
        );

        $result = $service->getRedirect($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(DeploymentNotFoundException::class, $result->exception);
        self::assertSame('No deployment found for the given domain name and context', $result->exception->getMessage());
    }

    #[Test]
    public function listRedirectsSuccess(): void
    {
        $caddyIds = ['1337', '7331'];
        $domain = 'yourhosting.nl';
        $context = Uuid::uuid4();
        $request = new ListRedirectsRequest($context);
        $request->requestId = 1;

        $expectedRedirects = [
            new RedirectRoute(
                id: $caddyIds[0],
                match: [],
                handle: [],
            ),
            new RedirectRoute(
                id: $caddyIds[1],
                match: [],
                handle: [],
            ),
        ];

        $expectedRedirectResults = [
            new GetRedirectResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::SUCCESS,
                redirect: new Redirect(
                    source: '',
                    destination: '',
                    redirectType: RedirectType::PERMANENT,
                ),
            ),
            new GetRedirectResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::SUCCESS,
                redirect: new Redirect(
                    source: '',
                    destination: '',
                    redirectType: RedirectType::TEMPORARY,
                ),
            ),
        ];

        $redirects = [];
        $redirects[] = $this->makeRedirectDeployment($domain, $context, $caddyIds[0]);
        $redirects[] = $this->makeRedirectDeployment('shop.' . $domain, $context, $caddyIds[1]);

        $redirectDeploymentRepo = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepo
            ->expects(self::once())
            ->method('findAllByContext')
            ->with($context)
            ->willReturn(new Collection($redirects));

        $this->caddyClient
            ->expects(self::exactly(2))
            ->method('getRedirect')
            ->with(
                ...self::withConsecutive(
                    [$caddyIds[0]],
                    [$caddyIds[1]],
                ),
            )
            ->willReturnOnConsecutiveCalls(
                $expectedRedirects[0],
                $expectedRedirects[1],
            );

        $caddyMapper = self::createMock(CaddyProvisionClientMapper::class);
        $caddyMapper
            ->expects(self::exactly(2))
            ->method('getRedirectDtoFromCaddyDto')
            ->with(
                ...self::withConsecutive(
                    [$expectedRedirects[0]],
                    [$expectedRedirects[1]],
                ),
            )
            ->willReturnOnConsecutiveCalls(
                $expectedRedirectResults[0]->redirect,
                $expectedRedirectResults[1]->redirect,
            );

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: self::createStub(LoggerInterface::class),
            redirectDeploymentRepository: $redirectDeploymentRepo,
            redirectContextRepository: self::createStub(RedirectContextRepository::class),
            caddyRedirectDeploymentRepository: self::createStub(CaddyRedirectDeploymentRepository::class),
            caddyProvisionClientMapper: $caddyMapper,
        );

        $result = $service->listRedirects($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNotNull($result->redirects);
        self::assertCount(2, $result->redirects);
        self::assertSame($domain, $result->redirects[0]->redirect?->source);
        self::assertSame('shop.' . $domain, $result->redirects[1]->redirect?->source);
    }

    #[Test]
    public function listRedirectsSaloonException(): void
    {
        $caddyIds = ['1337', '7331'];
        $domain = 'yourhosting.nl';
        $context = Uuid::uuid4();
        $request = new ListRedirectsRequest($context);
        $request->requestId = 1;

        $redirects = [];
        $redirects[] = $this->makeRedirectDeployment($domain, $context, $caddyIds[0]);
        $redirects[] = $this->makeRedirectDeployment('shop.' . $domain, $context, $caddyIds[1]);

        $redirectDeploymentRepo = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepo
            ->expects(self::once())
            ->method('findAllByContext')
            ->with($context)
            ->willReturn(new Collection($redirects));

        $this->caddyClient
            ->expects(self::exactly(2))
            ->method('getRedirect')
            ->willThrowException($exception = new SaloonException('Failed to get redirect'));

        $caddyMapper = self::createMock(CaddyProvisionClientMapper::class);
        $caddyMapper->expects(self::never())->method('getRedirectDtoFromCaddyDto');

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(self::exactly(2))
            ->method('error')
            ->with(
                ...self::withConsecutive(
                    [
                        sprintf('Could not retrieve redirects by key: [%s]', $caddyIds[0]),
                        [
                            LoggingContextKeys::EXCEPTION => $exception,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_CONTEXT => $context,
                            LoggingContextKeys::PROVISIONING_REQUEST_ID => $request->requestId,
                            LoggingContextKeys::META => [
                                'caddy_id' => $caddyIds[0],
                            ],
                        ],
                    ],
                    [
                        sprintf('Could not retrieve redirects by key: [%s]', $caddyIds[1]),
                        [
                            LoggingContextKeys::EXCEPTION => $exception,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_CONTEXT => $context,
                            LoggingContextKeys::PROVISIONING_REQUEST_ID => $request->requestId,
                            LoggingContextKeys::META => [
                                'caddy_id' => $caddyIds[1],
                            ],
                        ],
                    ],
                ),
            );

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: $logger,
            redirectDeploymentRepository: $redirectDeploymentRepo,
            redirectContextRepository: self::createStub(RedirectContextRepository::class),
            caddyRedirectDeploymentRepository: self::createStub(CaddyRedirectDeploymentRepository::class),
            caddyProvisionClientMapper: $caddyMapper,
        );

        $result = $service->listRedirects($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNotNull($result->redirects);
        self::assertCount(2, $result->redirects);
        foreach ($result->redirects as $redirect) {
            self::assertInstanceOf(SaloonException::class, $redirect->exception);
            self::assertSame('Failed to get redirect', $redirect->exception->getMessage());
        }
    }

    #[Test]
    public function listRedirectsNoDeploymentFound(): void
    {
        $context = Uuid::uuid4();
        $request = new ListRedirectsRequest($context);

        $redirectDeploymentRepo = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepo
            ->expects(self::once())
            ->method('findAllByContext')
            ->with($context)
            ->willReturn(new Collection());

        $this->caddyClient->expects(self::never())->method('getRedirect');

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: self::createStub(LoggerInterface::class),
            redirectDeploymentRepository: $redirectDeploymentRepo,
            redirectContextRepository: self::createStub(RedirectContextRepository::class),
            caddyRedirectDeploymentRepository: self::createStub(CaddyRedirectDeploymentRepository::class),
            caddyProvisionClientMapper: self::createStub(CaddyProvisionClientMapper::class),
        );

        $result = $service->listRedirects($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
        self::assertNotNull($result->redirects);
        self::assertCount(0, $result->redirects);
    }

    #[Test]
    public function updateRedirectSuccessNoParameter(): void
    {
        $oldSource = 'www.yourhosting.com';
        $newSource = 'donkey.yourhosting.com';
        $originalDestination = 'old-destination.com';
        $updatedDestination = 'versio.com';
        $context = Uuid::uuid4();
        $existingCaddyId = 'redirect:existing-caddy-id';

        $updateProvisioningRequest = new ProvisioningRequestFactory()
            ->redirect()
            ->makeOne([
                'request_name' => ProvisionRequestName::UPDATE_REDIRECT,
                'id' => 123,
            ]);

        $updateRedirectRequest = new UpdateRedirectRequest(
            oldSource: $oldSource,
            newSource: $newSource,
            destinationUrl: $updatedDestination,
            redirectType: RedirectType::PERMANENT,
            context: $context,
        );
        $updateRedirectRequest->requestId = $updateProvisioningRequest->id;

        $existingRedirectDeployment = RedirectDeploymentFactory::new()->makeOne([
            'source' => $oldSource,
            'destination' => $originalDestination,
            'type' => RedirectType::TEMPORARY,
            'context_uuid' => $context->toString(),
        ])->setRelation(
            'caddyRedirectDeployment',
            CaddyRedirectDeploymentFactory::new()->makeOne([
                'caddy_id' => $existingCaddyId,
            ]),
        );

        RedirectDeploymentFactory::new()->makeOne([
            'id' => $existingRedirectDeployment->id,
            'source' => $oldSource,
            'destination' => $updatedDestination,
            'type' => RedirectType::PERMANENT,
            'context_uuid' => $context->toString(),
        ])->setRelation(
            'caddyRedirectDeployment',
            CaddyRedirectDeploymentFactory::new()->makeOne([
                'caddy_id' => $existingCaddyId,
            ]),
        );

        $redirectDeploymentRepository = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepository
            ->expects(self::once())
            ->method('findBySourceAndContext')
            ->with($oldSource, $context)
            ->willReturn($existingRedirectDeployment);

        $redirectDeploymentRepository
            ->expects(self::once())
            ->method('update')
            ->with(
                redirectDeployment: $existingRedirectDeployment,
                requestId: $updateRedirectRequest->requestId,
                source: $newSource,
                destination: $updatedDestination,
                type: RedirectType::PERMANENT,
            );

        $this->caddyClient
            ->expects(self::once())
            ->method('updateRedirect')
            ->with(
                $existingCaddyId,
                $newSource,
                $updatedDestination,
                CaddyRedirectType::MOVED_PERMANENTLY,
                null,
                null,
            )
            ->willReturn($existingCaddyId);

        $caddyProvisionClientMapper = self::createMock(CaddyProvisionClientMapper::class);
        $caddyProvisionClientMapper
            ->expects(self::once())
            ->method('getCaddyRedirectType')
            ->with(RedirectType::PERMANENT)
            ->willReturn(CaddyRedirectType::MOVED_PERMANENTLY);

        $caddyProvisionClientMapper
            ->expects(self::once())
            ->method('parseSourceMatchers')
            ->with($updateRedirectRequest->newSource)
            ->willReturn(new RedirectSourceMatchers(
                host: $newSource,
            ));

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: self::createStub(LoggerInterface::class),
            redirectDeploymentRepository: $redirectDeploymentRepository,
            redirectContextRepository: self::createStub(RedirectContextRepository::class),
            caddyRedirectDeploymentRepository: self::createStub(CaddyRedirectDeploymentRepository::class),
            caddyProvisionClientMapper: $caddyProvisionClientMapper,
        );

        $result = $service->updateRedirect($updateRedirectRequest);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    #[Test]
    public function updateRedirectSuccessPathWithParameter(): void
    {
        $oldSource = 'www.yourhosting.com';
        $newHost = 'donkey.yourhosting.com';
        $newSource = $newHost . '/products';
        $originalDestination = 'old-destination.com';
        $updatedDestination = 'versio.com';
        $context = Uuid::uuid4();
        $existingCaddyId = 'redirect:existing-caddy-id';

        $updateProvisioningRequest = new ProvisioningRequestFactory()
            ->redirect()
            ->makeOne([
                'request_name' => ProvisionRequestName::UPDATE_REDIRECT,
                'id' => 123,
            ]);

        $updateRedirectRequest = new UpdateRedirectRequest(
            oldSource: $oldSource,
            newSource: $newSource,
            destinationUrl: $updatedDestination,
            redirectType: RedirectType::PERMANENT,
            context: $context,
        );
        $updateRedirectRequest->requestId = $updateProvisioningRequest->id;

        $existingRedirectDeployment = RedirectDeploymentFactory::new()->makeOne([
            'source' => $oldSource,
            'destination' => $originalDestination,
            'type' => RedirectType::TEMPORARY,
            'context_uuid' => $context->toString(),
        ])->setRelation(
            'caddyRedirectDeployment',
            CaddyRedirectDeploymentFactory::new()->makeOne([
                'caddy_id' => $existingCaddyId,
            ]),
        );

        RedirectDeploymentFactory::new()->makeOne([
            'id' => $existingRedirectDeployment->id,
            'source' => $oldSource,
            'destination' => $updatedDestination,
            'type' => RedirectType::PERMANENT,
            'context_uuid' => $context->toString(),
        ])->setRelation(
            'caddyRedirectDeployment',
            CaddyRedirectDeploymentFactory::new()->makeOne([
                'caddy_id' => $existingCaddyId,
            ]),
        );

        $redirectDeploymentRepository = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepository
            ->expects(self::once())
            ->method('findBySourceAndContext')
            ->with($oldSource, $context)
            ->willReturn($existingRedirectDeployment);

        $redirectDeploymentRepository
            ->expects(self::once())
            ->method('update')
            ->with(
                redirectDeployment: $existingRedirectDeployment,
                requestId: $updateRedirectRequest->requestId,
                source: $newSource,
                destination: $updatedDestination,
                type: RedirectType::PERMANENT,
            );

        $this->caddyClient
            ->expects(self::once())
            ->method('updateRedirect')
            ->with(
                $existingCaddyId,
                $newHost,
                $updatedDestination,
                CaddyRedirectType::MOVED_PERMANENTLY,
                ['/products'],
                null,
            )
            ->willReturn($existingCaddyId);

        $caddyProvisionClientMapper = self::createMock(CaddyProvisionClientMapper::class);
        $caddyProvisionClientMapper
            ->expects(self::once())
            ->method('getCaddyRedirectType')
            ->with(RedirectType::PERMANENT)
            ->willReturn(CaddyRedirectType::MOVED_PERMANENTLY);

        $caddyProvisionClientMapper
            ->expects(self::once())
            ->method('parseSourceMatchers')
            ->with($updateRedirectRequest->newSource)
            ->willReturn(new RedirectSourceMatchers(
                host: $newHost,
                paths: ['/products'],
            ));

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: self::createStub(LoggerInterface::class),
            redirectDeploymentRepository: $redirectDeploymentRepository,
            redirectContextRepository: self::createStub(RedirectContextRepository::class),
            caddyRedirectDeploymentRepository: self::createStub(CaddyRedirectDeploymentRepository::class),
            caddyProvisionClientMapper: $caddyProvisionClientMapper,
        );

        $result = $service->updateRedirect($updateRedirectRequest);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    public function testUpdateRedirectSuccessPathAndQuery(): void
    {
        $oldSource = 'www.yourhosting.com';
        $newHost = 'donkey.yourhosting.com';
        $newSource = $newHost . '/products?x=1&x=2';
        $originalDestination = 'old-destination.com';
        $updatedDestination = 'versio.com';
        $context = Uuid::uuid4();
        $existingCaddyId = 'redirect:existing-caddy-id';

        $updateProvisioningRequest = new ProvisioningRequestFactory()
            ->redirect()
            ->makeOne([
                'request_name' => ProvisionRequestName::UPDATE_REDIRECT,
                'id' => 123,
            ]);

        $updateRedirectRequest = new UpdateRedirectRequest(
            oldSource: $oldSource,
            newSource: $newSource,
            destinationUrl: $updatedDestination,
            redirectType: RedirectType::PERMANENT,
            context: $context,
        );
        $updateRedirectRequest->requestId = $updateProvisioningRequest->id;

        $existingRedirectDeployment = RedirectDeploymentFactory::new()->makeOne([
            'source' => $oldSource,
            'destination' => $originalDestination,
            'type' => RedirectType::TEMPORARY,
            'context_uuid' => $context->toString(),
        ])->setRelation(
            'caddyRedirectDeployment',
            CaddyRedirectDeploymentFactory::new()->makeOne([
                'caddy_id' => $existingCaddyId,
            ]),
        );

        $redirectDeploymentRepository = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepository
            ->expects(self::once())
            ->method('findBySourceAndContext')
            ->with($oldSource, $context)
            ->willReturn($existingRedirectDeployment);

        $redirectDeploymentRepository
            ->expects(self::once())
            ->method('update')
            ->with(
                redirectDeployment: $existingRedirectDeployment,
                requestId: $updateRedirectRequest->requestId,
                source: $newSource,
                destination: $updatedDestination,
                type: RedirectType::PERMANENT,
            );

        $this->caddyClient
            ->expects(self::once())
            ->method('updateRedirect')
            ->with(
                $existingCaddyId,
                $newHost,
                $updatedDestination,
                CaddyRedirectType::MOVED_PERMANENTLY,
                ['/products'],
                ['x' => ['1', '2']],
            )
            ->willReturn($existingCaddyId);

        $caddyProvisionClientMapper = self::createMock(CaddyProvisionClientMapper::class);
        $caddyProvisionClientMapper
            ->expects(self::once())
            ->method('getCaddyRedirectType')
            ->with(RedirectType::PERMANENT)
            ->willReturn(CaddyRedirectType::MOVED_PERMANENTLY);

        $caddyProvisionClientMapper
            ->expects(self::once())
            ->method('parseSourceMatchers')
            ->with($updateRedirectRequest->newSource)
            ->willReturn(new RedirectSourceMatchers(
                host: $newHost,
                paths: ['/products'],
                query: ['x' => ['1', '2']],
            ));

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: self::createStub(LoggerInterface::class),
            redirectDeploymentRepository: $redirectDeploymentRepository,
            redirectContextRepository: self::createStub(RedirectContextRepository::class),
            caddyRedirectDeploymentRepository: self::createStub(CaddyRedirectDeploymentRepository::class),
            caddyProvisionClientMapper: $caddyProvisionClientMapper,
        );

        $result = $service->updateRedirect($updateRedirectRequest);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    #[Test]
    public function updateRedirectClientException(): void
    {
        $oldSource = 'www.yourhosting.com';
        $newSource = 'donkey.yourhosting.com';
        $originalDestination = 'old-destination.com';
        $updatedDestination = 'versio.com';
        $context = Uuid::uuid4();
        $existingCaddyId = 'redirect:existing-caddy-id';

        $updateProvisioningRequest = new ProvisioningRequestFactory()
            ->redirect()
            ->makeOne([
                'request_name' => ProvisionRequestName::UPDATE_REDIRECT,
                'id' => 123,
            ]);

        $updateRedirectRequest = new UpdateRedirectRequest(
            oldSource: $oldSource,
            newSource: $newSource,
            destinationUrl: $updatedDestination,
            redirectType: RedirectType::PERMANENT,
            context: $context,
        );
        $updateRedirectRequest->requestId = $updateProvisioningRequest->id;

        $existingRedirectDeployment = RedirectDeploymentFactory::new()->makeOne([
            'source' => $oldSource,
            'destination' => $originalDestination,
            'type' => RedirectType::TEMPORARY,
            'context_uuid' => $context->toString(),
        ])->setRelation(
            'caddyRedirectDeployment',
            CaddyRedirectDeploymentFactory::new()->makeOne([
                'caddy_id' => $existingCaddyId,
            ]),
        );

        $requestException = self::createStub(RequestException::class);

        $redirectDeploymentRepository = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepository
            ->expects(self::once())
            ->method('findBySourceAndContext')
            ->with($oldSource, $context)
            ->willReturn($existingRedirectDeployment);

        $redirectDeploymentRepository->expects(self::never())->method('update');

        $this->caddyClient
            ->expects(self::once())
            ->method('updateRedirect')
            ->with(
                $existingCaddyId,
                $newSource,
                $updatedDestination,
                CaddyRedirectType::MOVED_PERMANENTLY,
                null,
                null,
            )
            ->willThrowException($requestException);

        $caddyProvisionClientMapper = self::createMock(CaddyProvisionClientMapper::class);
        $caddyProvisionClientMapper
            ->expects(self::once())
            ->method('getCaddyRedirectType')
            ->with(RedirectType::PERMANENT)
            ->willReturn(CaddyRedirectType::MOVED_PERMANENTLY);

        $caddyProvisionClientMapper
            ->expects(self::once())
            ->method('parseSourceMatchers')
            ->with($updateRedirectRequest->newSource)
            ->willReturn(new RedirectSourceMatchers(
                host: $newSource,
            ));

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: self::createStub(LoggerInterface::class),
            redirectDeploymentRepository: $redirectDeploymentRepository,
            redirectContextRepository: self::createStub(RedirectContextRepository::class),
            caddyRedirectDeploymentRepository: self::createStub(CaddyRedirectDeploymentRepository::class),
            caddyProvisionClientMapper: $caddyProvisionClientMapper,
        );

        $result = $service->updateRedirect($updateRedirectRequest);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($requestException, $result->exception);
    }

    #[Test]
    public function deleteRedirectSuccess(): void
    {
        $caddyId = '1337';
        $domain = 'yourhosting.nl';
        $context = Uuid::uuid4();
        $request = new DeleteRedirectRequest($domain, $context);
        $request->requestId = 1;

        $redirecDeployment = $this->makeRedirectDeployment($domain, $context, $caddyId);

        $redirectDeploymentRepo = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepo
            ->expects(self::once())
            ->method('findBySourceAndContext')
            ->with($domain, $context)
            ->willReturn($redirecDeployment);

        $this->caddyClient->expects(self::once())->method('deleteRedirect')->with($caddyId);

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: self::createStub(LoggerInterface::class),
            redirectDeploymentRepository: $redirectDeploymentRepo,
            redirectContextRepository: self::createStub(RedirectContextRepository::class),
            caddyRedirectDeploymentRepository: self::createStub(CaddyRedirectDeploymentRepository::class),
            caddyProvisionClientMapper: self::createStub(CaddyProvisionClientMapper::class),
        );

        $result = $service->deleteRedirect($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
    }

    #[Test]
    public function deleteRedirectThrowsSaloonException(): void
    {
        $caddyId = '1337';
        $domain = 'yourhosting.nl';
        $context = Uuid::uuid4();
        $request = new DeleteRedirectRequest($domain, $context);
        $request->requestId = 1;

        $redirecDeployment = $this->makeRedirectDeployment($domain, $context, $caddyId);

        $redirectDeploymentRepo = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepo
            ->expects(self::once())
            ->method('findBySourceAndContext')
            ->with($domain, $context)
            ->willReturn($redirecDeployment);

        $this->caddyClient
            ->expects(self::once())
            ->method('deleteRedirect')
            ->willThrowException($exception = new SaloonException('Failed to delete redirect'));

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                sprintf('Could not retrieve redirects by key: [%s]', $caddyId),
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                    LoggingContextKeys::PROVISIONING_CONTEXT => $context,
                    LoggingContextKeys::PROVISIONING_REQUEST_ID => $request->requestId,
                    LoggingContextKeys::META => [
                        'caddy_id' => $caddyId,
                    ],
                ],
            );

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: $logger,
            redirectDeploymentRepository: $redirectDeploymentRepo,
            redirectContextRepository: self::createStub(RedirectContextRepository::class),
            caddyRedirectDeploymentRepository: self::createStub(CaddyRedirectDeploymentRepository::class),
            caddyProvisionClientMapper: self::createStub(CaddyProvisionClientMapper::class),
        );

        $result = $service->deleteRedirect($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(SaloonException::class, $result->exception);
        self::assertSame('Failed to delete redirect', $result->exception->getMessage());
    }

    #[Test]
    public function deleteRedirectNoDeploymentFound(): void
    {
        $domain = 'yourhosting.nl';
        $context = Uuid::uuid4();
        $request = new DeleteRedirectRequest($domain, $context);
        $request->requestId = 1;

        $redirectDeploymentRepo = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepo
            ->expects(self::once())
            ->method('findBySourceAndContext')
            ->with($domain, $context)
            ->willReturn(null);

        $this->caddyClient->expects(self::never())->method('getRedirect');

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: self::createStub(LoggerInterface::class),
            redirectDeploymentRepository: $redirectDeploymentRepo,
            redirectContextRepository: self::createStub(RedirectContextRepository::class),
            caddyRedirectDeploymentRepository: self::createStub(CaddyRedirectDeploymentRepository::class),
            caddyProvisionClientMapper: self::createStub(CaddyProvisionClientMapper::class),
        );

        $result = $service->deleteRedirect($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    #[Test]
    public function terminateRedirectsSuccess(): void
    {
        $caddyIds = ['1337', '7331'];
        $domain = 'yourhosting.nl';
        $context = Uuid::uuid4();
        $request = new TerminateRedirectsRequest($context);
        $request->requestId = 1;

        $redirects = [];
        $redirects[] = $this->makeRedirectDeployment($domain, $context, $caddyIds[0]);
        $redirects[] = $this->makeRedirectDeployment('shop.' . $domain, $context, $caddyIds[1]);

        $redirectDeploymentRepo = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepo
            ->expects(self::once())
            ->method('findAllByContext')
            ->with($context)
            ->willReturn(new Collection($redirects));

        $this->caddyClient
            ->expects(self::exactly(2))
            ->method('deleteRedirect')
            ->with(
                ...self::withConsecutive(
                    [$caddyIds[0]],
                    [$caddyIds[1]],
                ),
            );

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: self::createStub(LoggerInterface::class),
            redirectDeploymentRepository: $redirectDeploymentRepo,
            redirectContextRepository: self::createStub(RedirectContextRepository::class),
            caddyRedirectDeploymentRepository: self::createStub(CaddyRedirectDeploymentRepository::class),
            caddyProvisionClientMapper: self::createStub(CaddyProvisionClientMapper::class),
        );

        $result = $service->terminateRedirects($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
    }

    #[Test]
    public function terminateRedirectsSaloonException(): void
    {
        $caddyIds = ['1337', '7331'];
        $domain = 'yourhosting.nl';
        $context = Uuid::uuid4();
        $request = new TerminateRedirectsRequest($context);
        $request->requestId = 1;

        $redirects = [];
        $redirects[] = $this->makeRedirectDeployment($domain, $context, $caddyIds[0]);
        $redirects[] = $this->makeRedirectDeployment('shop.' . $domain, $context, $caddyIds[1]);

        $redirectDeploymentRepo = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepo
            ->expects(self::once())
            ->method('findAllByContext')
            ->with($context)
            ->willReturn(new Collection($redirects));

        $expectedMessage = 'Failed to delete redirect';
        $this->caddyClient
            ->expects(self::once())
            ->method('deleteRedirect')
            ->willThrowException($exception = new SaloonException($expectedMessage));

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                sprintf('Could not retrieve redirects by key: [%s]', $caddyIds[0]),
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                    LoggingContextKeys::PROVISIONING_CONTEXT => $context,
                    LoggingContextKeys::PROVISIONING_REQUEST_ID => $request->requestId,
                    LoggingContextKeys::META => [
                        'caddy_id' => $caddyIds[0],
                    ],
                ],
            );

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: $logger,
            redirectDeploymentRepository: $redirectDeploymentRepo,
            redirectContextRepository: self::createStub(RedirectContextRepository::class),
            caddyRedirectDeploymentRepository: self::createStub(CaddyRedirectDeploymentRepository::class),
            caddyProvisionClientMapper: self::createStub(CaddyProvisionClientMapper::class),
        );

        $result = $service->terminateRedirects($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(SaloonException::class, $result->exception);
        self::assertSame($expectedMessage, $result->exception->getMessage());
    }

    #[Test]
    public function terminateRedirectsNoDeploymentFound(): void
    {
        $context = Uuid::uuid4();
        $request = new TerminateRedirectsRequest($context);
        $request->requestId = 1;

        $redirectDeploymentRepo = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepo
            ->expects(self::once())
            ->method('findAllByContext')
            ->with($context)
            ->willReturn(new Collection());

        $this->caddyClient->expects(self::never())->method('deleteRedirect');

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: self::createStub(LoggerInterface::class),
            redirectDeploymentRepository: $redirectDeploymentRepo,
            redirectContextRepository: self::createStub(RedirectContextRepository::class),
            caddyRedirectDeploymentRepository: self::createStub(CaddyRedirectDeploymentRepository::class),
            caddyProvisionClientMapper: self::createStub(CaddyProvisionClientMapper::class),
        );

        $result = $service->terminateRedirects($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    #[Test]
    public function suspendRedirectsSuccess(): void
    {
        $context = Uuid::uuid4();
        $request = new SuspendRedirectRequest($context);
        $request->requestId = 1234;

        $caddyIds = ['1337', '7331'];

        $firstDeployment = $this->makeRedirectDeployment('yourhosting.nl', $context, $caddyIds[0]);
        $secondDeployment = $this->makeRedirectDeployment('shop.yourhosting.nl', $context, $caddyIds[1]);

        $deployments = new Collection([
            $firstDeployment,
            $secondDeployment,
        ]);

        $redirectDeploymentRepository = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepository
            ->expects(self::once())
            ->method('findAllByContext')
            ->with($context)
            ->willReturn($deployments);

        $this->caddyClient
            ->expects(self::exactly(2))
            ->method('deleteRedirect')
            ->with(
                ...self::withConsecutive(
                    [$caddyIds[0]],
                    [$caddyIds[1]],
                ),
            );

        $caddyRedirectDeploymentRepository = self::createMock(CaddyRedirectDeploymentRepository::class);
        $caddyRedirectDeploymentRepository
            ->expects(self::exactly(2))
            ->method('forceDelete')
            ->with(
                ...self::withConsecutive(
                    [$firstDeployment->caddyRedirectDeployment],
                    [$secondDeployment->caddyRedirectDeployment],
                ),
            );

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: self::createStub(LoggerInterface::class),
            redirectDeploymentRepository: $redirectDeploymentRepository,
            redirectContextRepository: self::createStub(RedirectContextRepository::class),
            caddyRedirectDeploymentRepository: $caddyRedirectDeploymentRepository,
            caddyProvisionClientMapper: self::createStub(CaddyProvisionClientMapper::class),
        );

        $result = $service->suspendRedirects($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    #[Test]
    public function suspendRedirectsNoDeploymentsFoundReturnsSuccess(): void
    {
        $context = Uuid::uuid4();
        $request = new SuspendRedirectRequest($context);
        $request->requestId = 1234;

        $redirectDeploymentRepository = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepository
            ->expects(self::once())
            ->method('findAllByContext')
            ->with($context)
            ->willReturn(new Collection());

        $this->caddyClient->expects(self::never())->method('deleteRedirect');

        $caddyRedirectDeploymentRepository = self::createMock(CaddyRedirectDeploymentRepository::class);
        $caddyRedirectDeploymentRepository->expects(self::never())->method('forceDelete');

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: self::createStub(LoggerInterface::class),
            redirectDeploymentRepository: $redirectDeploymentRepository,
            redirectContextRepository: self::createStub(RedirectContextRepository::class),
            caddyRedirectDeploymentRepository: $caddyRedirectDeploymentRepository,
            caddyProvisionClientMapper: self::createStub(CaddyProvisionClientMapper::class),
        );

        $result = $service->suspendRedirects($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    #[Test]
    public function suspendRedirectsFailsOnDeleteRedirectExternalError(): void
    {
        $context = Uuid::uuid4();
        $request = new SuspendRedirectRequest($context);
        $request->requestId = 1234;

        $deployment = $this->makeRedirectDeployment('yourhosting.nl', $context, '1337');

        $redirectDeploymentRepository = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepository
            ->expects(self::once())
            ->method('findAllByContext')
            ->with($context)
            ->willReturn(new Collection([$deployment]));

        $expectedException = new SaloonException('Something went wrong');

        $this->caddyClient
            ->expects(self::once())
            ->method('deleteRedirect')
            ->with('1337')
            ->willThrowException($expectedException);

        $caddyRedirectDeploymentRepository = self::createMock(CaddyRedirectDeploymentRepository::class);
        $caddyRedirectDeploymentRepository->expects(self::never())->method('forceDelete');

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: $logger,
            redirectDeploymentRepository: $redirectDeploymentRepository,
            redirectContextRepository: self::createStub(RedirectContextRepository::class),
            caddyRedirectDeploymentRepository: $caddyRedirectDeploymentRepository,
            caddyProvisionClientMapper: self::createStub(CaddyProvisionClientMapper::class),
        );

        $result = $service->suspendRedirects($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($expectedException, $result->exception);
    }

    #[Test]
    public function unsuspendRedirectsSuccess(): void
    {
        $context = Uuid::uuid4();
        $request = new UnsuspendRedirectRequest($context);
        $request->requestId = 1234;

        $deployment = RedirectDeploymentFactory::new()->makeOne([
            'source' => 'yourhosting.nl/products',
            'destination' => 'https://versio.com/shop',
            'type' => RedirectType::PERMANENT,
            'context_uuid' => $context->toString(),
        ])->setRelation('caddyRedirectDeployment', null);

        $redirectDeploymentRepository = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepository
            ->expects(self::once())
            ->method('findAllByContext')
            ->with($context)
            ->willReturn(new Collection([$deployment]));

        $caddyProvisionClientMapper = self::createMock(CaddyProvisionClientMapper::class);
        $caddyProvisionClientMapper
            ->expects(self::once())
            ->method('getCaddyRedirectType')
            ->with(RedirectType::PERMANENT)
            ->willReturn(CaddyRedirectType::MOVED_PERMANENTLY);

        $caddyProvisionClientMapper
            ->expects(self::once())
            ->method('parseSourceMatchers')
            ->with($deployment->source)
            ->willReturn(new RedirectSourceMatchers(
                host: 'yourhosting.nl',
                paths: ['/products'],
            ));

        $this->caddyClient
            ->expects(self::once())
            ->method('createRedirect')
            ->with(
                'yourhosting.nl',
                $deployment->destination,
                CaddyRedirectType::MOVED_PERMANENTLY,
                ['/products'],
                null,
            )
            ->willReturn('new-caddy-id');

        $restoredCaddyDeployment = CaddyRedirectDeploymentFactory::new()->makeOne([
            'caddy_id' => 'new-caddy-id',
        ]);

        $caddyRedirectDeploymentRepository = self::createMock(CaddyRedirectDeploymentRepository::class);
        $caddyRedirectDeploymentRepository
            ->expects(self::once())
            ->method('create')
            ->with($deployment, 'new-caddy-id')
            ->willReturn($restoredCaddyDeployment);

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: self::createStub(LoggerInterface::class),
            redirectDeploymentRepository: $redirectDeploymentRepository,
            redirectContextRepository: self::createStub(RedirectContextRepository::class),
            caddyRedirectDeploymentRepository: $caddyRedirectDeploymentRepository,
            caddyProvisionClientMapper: $caddyProvisionClientMapper,
        );

        $result = $service->unsuspendRedirects($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    public function testUnsuspendRedirectsSuccessPathAndQuery(): void
    {
        $context = Uuid::uuid4();
        $request = new UnsuspendRedirectRequest($context);
        $request->requestId = 1234;

        $deployment = RedirectDeploymentFactory::new()->makeOne([
            'source' => 'yourhosting.nl/products?x=1&x=2',
            'destination' => 'https://versio.com/shop',
            'type' => RedirectType::PERMANENT,
            'context_uuid' => $context->toString(),
        ])->setRelation('caddyRedirectDeployment', null);

        $redirectDeploymentRepository = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepository
            ->expects(self::once())
            ->method('findAllByContext')
            ->with($context)
            ->willReturn(new Collection([$deployment]));

        $caddyProvisionClientMapper = self::createMock(CaddyProvisionClientMapper::class);
        $caddyProvisionClientMapper
            ->expects(self::once())
            ->method('getCaddyRedirectType')
            ->with(RedirectType::PERMANENT)
            ->willReturn(CaddyRedirectType::MOVED_PERMANENTLY);

        $caddyProvisionClientMapper
            ->expects(self::once())
            ->method('parseSourceMatchers')
            ->with($deployment->source)
            ->willReturn(new RedirectSourceMatchers(
                host: 'yourhosting.nl',
                paths: ['/products'],
                query: ['x' => ['1', '2']],
            ));

        $this->caddyClient
            ->expects(self::once())
            ->method('createRedirect')
            ->with(
                'yourhosting.nl',
                $deployment->destination,
                CaddyRedirectType::MOVED_PERMANENTLY,
                ['/products'],
                ['x' => ['1', '2']],
            )
            ->willReturn('new-caddy-id');

        $restoredCaddyDeployment = CaddyRedirectDeploymentFactory::new()->makeOne([
            'caddy_id' => 'new-caddy-id',
        ]);

        $caddyRedirectDeploymentRepository = self::createMock(CaddyRedirectDeploymentRepository::class);
        $caddyRedirectDeploymentRepository
            ->expects(self::once())
            ->method('create')
            ->with($deployment, 'new-caddy-id')
            ->willReturn($restoredCaddyDeployment);

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: self::createStub(LoggerInterface::class),
            redirectDeploymentRepository: $redirectDeploymentRepository,
            redirectContextRepository: self::createStub(RedirectContextRepository::class),
            caddyRedirectDeploymentRepository: $caddyRedirectDeploymentRepository,
            caddyProvisionClientMapper: $caddyProvisionClientMapper,
        );

        $result = $service->unsuspendRedirects($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    #[Test]
    public function unsuspendRedirectsNoDeploymentsFoundReturnsSuccess(): void
    {
        $context = Uuid::uuid4();
        $request = new UnsuspendRedirectRequest($context);
        $request->requestId = 1234;

        $redirectDeploymentRepository = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepository
            ->expects(self::once())
            ->method('findAllByContext')
            ->with($context)
            ->willReturn(new Collection());

        $this->caddyClient->expects(self::never())->method('createRedirect');

        $caddyRedirectDeploymentRepository = self::createMock(CaddyRedirectDeploymentRepository::class);
        $caddyRedirectDeploymentRepository->expects(self::never())->method('create');

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: self::createStub(LoggerInterface::class),
            redirectDeploymentRepository: $redirectDeploymentRepository,
            redirectContextRepository: self::createStub(RedirectContextRepository::class),
            caddyRedirectDeploymentRepository: $caddyRedirectDeploymentRepository,
            caddyProvisionClientMapper: self::createStub(CaddyProvisionClientMapper::class),
        );

        $result = $service->unsuspendRedirects($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    #[Test]
    public function unsuspendRedirectsFailsOnCreateRedirectExternalError(): void
    {
        $context = Uuid::uuid4();
        $request = new UnsuspendRedirectRequest($context);
        $request->requestId = 1234;

        $deployment = RedirectDeploymentFactory::new()->makeOne([
            'source' => 'yourhosting.nl',
            'destination' => 'https://versio.com',
            'type' => RedirectType::PERMANENT,
            'context_uuid' => $context->toString(),
        ])->setRelation('caddyRedirectDeployment', null);

        $redirectDeploymentRepository = self::createMock(RedirectDeploymentRepository::class);
        $redirectDeploymentRepository
            ->expects(self::once())
            ->method('findAllByContext')
            ->with($context)
            ->willReturn(new Collection([$deployment]));

        $caddyProvisionClientMapper = self::createMock(CaddyProvisionClientMapper::class);
        $caddyProvisionClientMapper
            ->expects(self::once())
            ->method('getCaddyRedirectType')
            ->with(RedirectType::PERMANENT)
            ->willReturn(CaddyRedirectType::MOVED_PERMANENTLY);

        $caddyProvisionClientMapper
            ->expects(self::once())
            ->method('parseSourceMatchers')
            ->with($deployment->source)
            ->willReturn(new RedirectSourceMatchers(
                host: 'yourhosting.nl',
            ));

        $expectedException = self::createStub(RequestException::class);

        $this->caddyClient
            ->expects(self::once())
            ->method('createRedirect')
            ->with(
                'yourhosting.nl',
                $deployment->destination,
                CaddyRedirectType::MOVED_PERMANENTLY,
                null,
                null,
            )
            ->willThrowException($expectedException);

        $caddyRedirectDeploymentRepository = self::createMock(CaddyRedirectDeploymentRepository::class);
        $caddyRedirectDeploymentRepository->expects(self::never())->method('create');

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $service = new CaddyProvisionService(
            caddyClient: $this->caddyClient,
            logger: $logger,
            redirectDeploymentRepository: $redirectDeploymentRepository,
            redirectContextRepository: self::createStub(RedirectContextRepository::class),
            caddyRedirectDeploymentRepository: $caddyRedirectDeploymentRepository,
            caddyProvisionClientMapper: $caddyProvisionClientMapper,
        );

        $result = $service->unsuspendRedirects($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($expectedException, $result->exception);
    }

    private function makeRedirectDeployment(string $domain, UuidInterface $context, string $caddyId): RedirectDeployment
    {
        return new RedirectDeploymentFactory()->makeOne(
            [
                'source' => $domain,
                'context_uuid' => $context->toString(),
            ],
        )->setRelation(
            'caddyRedirectDeployment',
            new CaddyRedirectDeploymentFactory()->makeOne(['caddy_id' => $caddyId]),
        );
    }
}
