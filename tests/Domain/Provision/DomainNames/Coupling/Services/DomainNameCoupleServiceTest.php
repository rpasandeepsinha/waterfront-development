<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\DomainNames\Coupling\Services;

use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator as ValidatorContract;
use Mockery;  // @phpstan-ignore-line disallowed.namespace
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\TestCase;
use Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions\CreateDomainNameCoupleDeploymentException;
use Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions\DeleteDomainNameCoupleDeploymentException;
use Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions\ServiceNotInstanceOfDomainNameCoupleInterfaceException;
use Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions\UnknownDomainNameCoupleRequestException;
use Waterfront\Domain\Provision\DomainNames\Coupling\Interfaces\DomainNameCoupleInterface;
use Waterfront\Domain\Provision\DomainNames\Coupling\Models\DomainNameCoupleDeployment;
use Waterfront\Domain\Provision\DomainNames\Coupling\Repositories\DomainNameCoupleRepository;
use Waterfront\Domain\Provision\DomainNames\Coupling\Requests\DomainNameCoupleRequest;
use Waterfront\Domain\Provision\DomainNames\Coupling\Requests\DomainNameDecoupleRequest;
use Waterfront\Domain\Provision\DomainNames\Coupling\Results\DomainNameCoupleResult;
use Waterfront\Domain\Provision\DomainNames\Coupling\Services\DomainNameCoupleService;
use Waterfront\Domain\Provision\DomainNames\Coupling\Validators\DomainNameCoupleValidator;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Hosting\Services\HostingProvisionService;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Models\ProvisionDeployment;
use Waterfront\Domain\Provision\Repositories\ProvisioningDeploymentRepository;
use Waterfront\Domain\Provision\Results\ProvisionResult;

#[CoversClass(DomainNameCoupleService::class)]
class DomainNameCoupleServiceTest extends TestCase
{
    private UuidInterface $context;

    private DomainNameCoupleValidator&MockInterface $mockValidator;

    private DomainNameCoupleRepository&MockInterface $mockCoupleRepository;

    private ProvisioningDeploymentRepository&MockInterface $mockDeploymentRepository;

    private HostingProvisionService&DomainNameCoupleInterface&MockInterface $mockHostingService;

    private DomainNameCoupleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Str::uuid();

        $this->mockValidator  = self::mock(DomainNameCoupleValidator::class);
        $this->mockCoupleRepository = self::mock(DomainNameCoupleRepository::class);
        $this->mockDeploymentRepository = self::mock(ProvisioningDeploymentRepository::class);

        /**
         * Mock the HostingProvisionService to simulate the coupling process.
         * We need to use mockery to mock the service as it implements the
         * DomainNameCoupleInterface, which is required for the coupling.
         *
         * @var HostingProvisionService&DomainNameCoupleInterface&MockInterface $mockHostingService
         *
         * @phpstan-ignore disallowed.namespace
         */
        $mockHostingService =  Mockery::mock(
            HostingProvisionService::class,
            DomainNameCoupleInterface::class
        );

        $this->mockHostingService = $mockHostingService;

        $this->service = new DomainNameCoupleService(
            $this->mockValidator,
            $this->mockHostingService,
            $this->mockCoupleRepository,
            $this->mockDeploymentRepository
        );
    }

    #[Test]
    public function getDefaultProvider(): void
    {
        self::assertSame(ProvisionProvider::INTERNAL, $this->service->getDefaultProvider());
    }

    #[Test]
    public function validateReturnsNullWhenValidatorPasses(): void
    {
        $request =  $this->createStub(
            ProvisionRequestInterface::class
        );

        $validator = self::mock(ValidatorContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('fails')
                ->once()
                ->andReturn(false);
        });

        $this->mockValidator
            ->shouldReceive('getValidatorByRequest')->once()->with($request)
            ->andReturn($validator);

        self::assertNull($this->service->validate($request));
    }

    #[Test]
    public function validateReturnsValidationErrorWhenValidatorFails(): void
    {
        $request =  $this->createStub(
            ProvisionRequestInterface::class
        );

        $validator = self::mock(ValidatorContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('fails')
                ->once()
                ->andReturn(true);
        });

        $errorBag = self::mock(MessageBag::class, function (MockInterface $mock) {
            $mock->shouldReceive('messages')->andReturn([]);
        });

        $validator->shouldReceive('errors')->andReturn($errorBag);

        $this->mockValidator
            ->shouldReceive('getValidatorByRequest')->once()->with($request)
            ->andReturn($validator);

        $result = $this->service->validate($request);

        self::assertInstanceOf(ProvisionResult::class, $result);
        self::assertSame(ProvisionStatus::VALIDATION_ERROR, $result->provisionStatus);
    }

    #[Test]
    public function coupleDomainNameReturnsFailedWithException(): void
    {
        $domain = 'xyz.net';
        $deploymentRequestUuid = Uuid::uuid4();
        $coupleDeploymentUuid = Uuid::uuid4();
        $requestId = 1337;

        $provisionDeployment = self::mock(ProvisionDeployment::class);
        $provisionDeployment->shouldReceive('getAttribute')->with('uuid')->andReturn($deploymentRequestUuid);
        $provisionDeployment
            ->shouldReceive('getAttribute')
            ->with('request')
            ->andReturn(
                ProvisioningRequestFactory::new()->hosting()->makeOne()
            );

        $modelThatWontCreate = new DomainNameCoupleDeployment();
        $modelThatWontCreate->domain = $domain;
        $modelThatWontCreate->couple_type = ProvisionType::HOSTING;
        $modelThatWontCreate->uuid = $coupleDeploymentUuid;

        $request = new DomainNameCoupleRequest($domain, $deploymentRequestUuid, $this->context);
        $request->requestId = $requestId;

        $this->mockDeploymentRepository
            ->shouldReceive('findDeploymentByRequestUuid')
            ->once()
            ->with($deploymentRequestUuid)
            ->andReturn($provisionDeployment);

        $this->mockHostingService->shouldNotReceive('coupleDomainName');

        $thrownException = new CreateDomainNameCoupleDeploymentException($modelThatWontCreate);

        $this->mockCoupleRepository
            ->shouldReceive('create')
            ->once()
            ->andThrow($thrownException);

        $result = $this->service->send($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(CreateDomainNameCoupleDeploymentException::class, $result->exception);
    }

    #[Test]
    public function decoupleDomainName(): void
    {
        $domain = 'test.org';
        $deploymentRequestUuid = Uuid::uuid4();

        $provisionDeployment = self::mock(ProvisionDeployment::class);
        $provisionDeployment->shouldReceive('getAttribute')->with('uuid')->andReturn($deploymentRequestUuid);
        $provisionDeployment
            ->shouldReceive('getAttribute')
            ->with('request')
            ->andReturn(
                ProvisioningRequestFactory::new()->hosting()->makeOne()
            );

        $request = new DomainNameDecoupleRequest($domain, $deploymentRequestUuid, $this->context);

        $this->mockDeploymentRepository
            ->shouldReceive('findDeploymentByRequestUuid')
            ->once()
            ->with($deploymentRequestUuid)
            ->andReturn($provisionDeployment);

        $this->mockHostingService
            ->shouldReceive('decoupleDomainName')
            ->once()
            ->andReturn(new ProvisionResult($request, ProvisionStatus::SUCCESS));

        $this->mockCoupleRepository
            ->shouldReceive('delete')
            ->once();

        $result = $this->service->send($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
    }

    #[Test]
    public function decoupleDomainNameDatabaseFailReturnsFailedResult(): void
    {
        $domain = 'xyz.net';
        $deploymentRequestUuid = Uuid::uuid4();
        $coupleDeploymentUuid = Uuid::uuid4();

        $provisionDeployment = self::mock(ProvisionDeployment::class);
        $provisionDeployment->shouldReceive('getAttribute')->with('uuid')->andReturn($deploymentRequestUuid);
        $provisionDeployment
            ->shouldReceive('getAttribute')
            ->with('request')
            ->andReturn(
                ProvisioningRequestFactory::new()->hosting()->makeOne()
            );

        $modelThatWontDelete = new DomainNameCoupleDeployment();
        $modelThatWontDelete->domain = $domain;
        $modelThatWontDelete->couple_type = ProvisionType::HOSTING;
        $modelThatWontDelete->uuid = $coupleDeploymentUuid;

        $request = new DomainNameDecoupleRequest($domain, $deploymentRequestUuid, $this->context);

        $this->mockDeploymentRepository
            ->shouldReceive('findDeploymentByRequestUuid')
            ->once()
            ->with($deploymentRequestUuid)
            ->andReturn($provisionDeployment);

        $this->mockHostingService
            ->shouldReceive('decoupleDomainName')
            ->once()
            ->andReturn(new ProvisionResult($request, ProvisionStatus::SUCCESS));

        $thrownException = new DeleteDomainNameCoupleDeploymentException($modelThatWontDelete);

        $this->mockCoupleRepository
            ->shouldReceive('delete')
            ->once()
            ->andThrow($thrownException);

        $result = $this->service->send($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(DeleteDomainNameCoupleDeploymentException::class, $result->exception);
    }

    #[Test]
    public function coupleDomainName(): void
    {
        $domain = 'foo.io';
        $requestDeploymentUuid = Uuid::uuid4();
        $coupleRequestId = 321;

        $provisionDeployment = self::mock(ProvisionDeployment::class);
        $provisionDeployment->shouldReceive('getAttribute')->with('uuid')->andReturn($requestDeploymentUuid);
        $provisionDeployment
            ->shouldReceive('getAttribute')
            ->with('request')
            ->andReturn(
                ProvisioningRequestFactory::new()->hosting()->makeOne()
            );

        $this->mockDeploymentRepository
            ->shouldReceive('findDeploymentByRequestUuid')
            ->once()
            ->with($requestDeploymentUuid)
            ->andReturn($provisionDeployment);

        $coupleRequest = new DomainNameCoupleRequest($domain, $requestDeploymentUuid, $this->context);
        $coupleRequest->requestId = $coupleRequestId;

        $coupleResult = self::createStub(DomainNameCoupleResult::class);
        $coupleResult->provisionStatus = ProvisionStatus::SUCCESS;

        $this->mockCoupleRepository
            ->shouldReceive('create')
            ->once()
            ->with($domain, ProvisionType::HOSTING, $requestDeploymentUuid, $coupleRequestId);

        $this->mockHostingService
            ->shouldReceive('coupleToDomainName')
            ->once()
            ->with($coupleRequest)
            ->andReturn($coupleResult);

        self::assertSame(ProvisionStatus::SUCCESS, $this->service->send($coupleRequest)->provisionStatus);
    }

    #[Test]
    public function sendThrowsForUnknownRequest(): void
    {
        $this->expectException(UnknownDomainNameCoupleRequestException::class);

        $unknownRequestType = $this->createStub(
            ProvisionRequestInterface::class
        );

        $this->service->send($unknownRequestType);
    }

    #[Test]
    public function getServiceFromProvisionTypeReturnsServiceWhenItImplementsInterface(): void
    {
        self::assertSame(
            $this->mockHostingService,
            $this->service->getServiceFromProvisionType(ProvisionType::HOSTING)
        );
    }

    #[Test]
    public function getServiceFromProvisionTypeThrowsWhenServiceNotImplementingInterface(): void
    {
        $this->expectException(ServiceNotInstanceOfDomainNameCoupleInterfaceException::class);

        $hostingServiceWithoutCoupleInterface = self::mock(HostingProvisionService::class);

        $svc = new DomainNameCoupleService($this->mockValidator, $hostingServiceWithoutCoupleInterface, $this->mockCoupleRepository, $this->mockDeploymentRepository);
        $svc->getServiceFromProvisionType(ProvisionType::HOSTING);
    }
}
