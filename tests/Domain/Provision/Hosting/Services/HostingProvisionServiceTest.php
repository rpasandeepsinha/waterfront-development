<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Hosting\Services;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Hosting\Exceptions\UnknownHostingProviderException;
use Waterfront\Domain\Provision\Hosting\Exceptions\UnknownHostingRequestException;
use Waterfront\Domain\Provision\Hosting\Factories\HostingServiceFactory;
use Waterfront\Domain\Provision\Hosting\Interfaces\HostingProvisionServiceInterface;
use Waterfront\Domain\Provision\Hosting\Requests\HostingCreateRequest;
use Waterfront\Domain\Provision\Hosting\Requests\HostingProvisionRequest;
use Waterfront\Domain\Provision\Hosting\Requests\HostingSsoRequest;
use Waterfront\Domain\Provision\Hosting\Results\HostingResult;
use Waterfront\Domain\Provision\Hosting\Services\HostingProvisionService;

#[CoversClass(HostingProvisionService::class)]
class HostingProvisionServiceTest extends TestCase
{
    private const ProvisionProvider DEFAULT_PROVIDER = ProvisionProvider::PLESK;

    private HostingCreateRequest&Stub $createRequestMock;

    private UuidInterface $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Str::uuid();

        $this->createRequestMock = self::createStub(HostingCreateRequest::class);
    }

    #[Test]
    public function sendingValidHostingRequestWillReturnResult(): void
    {
        $request = new HostingSsoRequest('username', $this->context);

        $mockHostingFactory = self::createMock(HostingServiceFactory::class);
        $mockHostingProvisionService = self::createMock(HostingProvisionServiceInterface::class);
        $hostingProvisionService = new HostingProvisionService($mockHostingFactory);

        $mockHostingFactory->expects(self::once())
            ->method('getProviderService')
            ->with(self::DEFAULT_PROVIDER)
            ->willReturn($mockHostingProvisionService);

        $mockHostingProvisionService->expects(self::once())
            ->method('getSso')
            ->with($request)
            ->willReturn(new HostingResult($request, ProvisionStatus::PENDING));

        $result = $hostingProvisionService->send($request);

        self::assertSame(ProvisionStatus::PENDING, $result->provisionStatus);
    }

    #[Test]
    public function sendingValidRequestWillReturnNull(): void
    {
        $this->createRequestMock->provider = self::DEFAULT_PROVIDER;

        $mockHostingFactory = self::createMock(HostingServiceFactory::class);
        $mockValidator = self::createMock(Validator::class);
        $hostingProvisionService = new HostingProvisionService($mockHostingFactory);

        $mockHostingFactory->expects(self::once())
            ->method('getValidator')
            ->with(self::DEFAULT_PROVIDER, $this->createRequestMock)
            ->willReturn($mockValidator);

        $mockValidator->expects(self::once())
            ->method('fails')
            ->willReturn(false);

        $result = $hostingProvisionService->validate($this->createRequestMock);

        self::assertNull($result);
    }

    #[Test]
    public function invalidRequest(): void
    {
        $invalidRequest = self::createStub(HostingProvisionRequest::class);
        $expectedExceptionMessage = sprintf('No implementation found in hosting service for request [%s]', $invalidRequest::class);

        $mockHostingFactory = self::createStub(HostingServiceFactory::class);
        $hostingProvisionService = new HostingProvisionService($mockHostingFactory);

        $result = $hostingProvisionService->send($invalidRequest);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(UnknownHostingRequestException::class, $result->exception);
        self::assertSame($expectedExceptionMessage, $result->exception->getMessage());
    }

    #[Test]
    public function invalidProvider(): void
    {
        $request = $this->createStub(HostingProvisionRequest::class);
        $invalidProvider = ProvisionProvider::RTR;
        $expectedExceptionMessage = sprintf("Can't resolve hosting service from unknown provider [%s]", $invalidProvider->value);

        $request->provider = $invalidProvider;

        $providerException = new UnknownHostingProviderException($invalidProvider);

        $mockHostingFactory = self::createMock(HostingServiceFactory::class);
        $hostingProvisionService = new HostingProvisionService($mockHostingFactory);

        $mockHostingFactory->expects(self::once())
            ->method('getProviderService')
            ->with($invalidProvider)
            ->willThrowException($providerException);

        $result = $hostingProvisionService->send($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(UnknownHostingProviderException::class, $result->exception);
        self::assertSame($expectedExceptionMessage, $result->exception->getMessage());
    }

    #[Test]
    public function validatorFailsResult(): void
    {
        $this->createRequestMock->provider = self::DEFAULT_PROVIDER;

        $validationErrors = ['field' => ['first-error', 'second-error']];
        $messageBag = new MessageBag($validationErrors);

        $mockHostingFactory = self::createMock(HostingServiceFactory::class);
        $mockHostingProvisionService = self::createMock(HostingProvisionServiceInterface::class);
        $mockValidator = self::createMock(Validator::class);
        $hostingProvisionService = new HostingProvisionService($mockHostingFactory);

        $mockHostingFactory->expects(self::once())
            ->method('getValidator')
            ->with(self::DEFAULT_PROVIDER, $this->createRequestMock)
            ->willReturn($mockValidator);

        $mockValidator->expects(self::once())
            ->method('fails')
            ->willReturn(true);

        $mockValidator->expects(self::once())
            ->method('errors')
            ->willReturn($messageBag);

        $mockHostingFactory->expects(self::never())
            ->method('getProviderService')
            ->with(self::DEFAULT_PROVIDER);

        $mockHostingProvisionService->expects(self::never())
            ->method('create')
            ->with($this->createRequestMock);

        $result = $hostingProvisionService->validate($this->createRequestMock);

        self::assertNotNull($result);
        self::assertSame(ProvisionStatus::VALIDATION_ERROR, $result->provisionStatus);
        self::assertNotNull($result->validationResult);
        self::assertSame($validationErrors, $result->validationResult->messages);
    }
}
