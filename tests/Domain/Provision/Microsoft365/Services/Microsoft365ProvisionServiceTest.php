<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Microsoft365\Services;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Hosting\Requests\HostingProvisionRequest;
use Waterfront\Domain\Provision\Interfaces\ProvisionResultInterface;
use Waterfront\Domain\Provision\Microsoft365\Exceptions\UnknownMicrosoft365RequestException;
use Waterfront\Domain\Provision\Microsoft365\Factories\Microsoft365ServiceFactory;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365TenantIdRequest;
use Waterfront\Domain\Provision\Microsoft365\Services\Microsoft365ProvisionService;
use Waterfront\Domain\Provision\Microsoft365\Services\MicrosoftGraphService;
use Waterfront\Domain\Provision\Microsoft365\Services\MicrosoftOnlineService;

#[CoversClass(Microsoft365ProvisionService::class)]
class Microsoft365ProvisionServiceTest extends TestCase
{
    private Microsoft365TenantIdRequest $tenantIdRequest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantIdRequest = new Microsoft365TenantIdRequest('tenant', Str::uuid());
    }

    #[Test]
    public function sendingValidMicrosoft365RequestWillReturnResult(): void
    {
        $mockTenantService = $this->createMock(MicrosoftOnlineService::class);
        $mockResult = $this->createStub(ProvisionResultInterface::class);

        $provisionService = new Microsoft365ProvisionService(
            $this->createStub(Microsoft365ServiceFactory::class),
            $mockTenantService,
            $this->createStub(MicrosoftGraphService::class),
        );

        $mockTenantService
            ->expects($this->once())
            ->method('getTenantId')
            ->with($this->tenantIdRequest)
            ->willReturn($mockResult);

        $result = $provisionService->send($this->tenantIdRequest);

        self::assertSame($mockResult, $result);
    }

    #[Test]
    public function sendingValidRequestWillReturnNullOnValidation(): void
    {
        $provisionService = new Microsoft365ProvisionService(
            $this->createStub(Microsoft365ServiceFactory::class),
            $this->createStub(MicrosoftOnlineService::class),
            $this->createStub(MicrosoftGraphService::class),
        );

        self::assertNull($provisionService->validate($this->tenantIdRequest));
    }

    #[Test]
    public function invalidRequest(): void
    {
        $invalidRequest = self::createStub(HostingProvisionRequest::class);
        $expectedExceptionMessage = sprintf(
            'No implementation found in Microsoft365 service for request [%s]',
            $invalidRequest::class,
        );

        $provisionService = new Microsoft365ProvisionService(
            $this->createStub(Microsoft365ServiceFactory::class),
            $this->createStub(MicrosoftOnlineService::class),
            $this->createStub(MicrosoftGraphService::class),
        );

        $result = $provisionService->send($invalidRequest);
        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(UnknownMicrosoft365RequestException::class, $result->exception);
        self::assertSame($expectedExceptionMessage, $result->exception->getMessage());
    }

    #[Test]
    public function validatorFailsResult(): void
    {
        $mockServiceFactory = $this->createMock(Microsoft365ServiceFactory::class);
        $mockValidator = $this->createMock(Validator::class);

        $validationErrors = ['field' => ['an-error', 'another-error']];
        $messageBag = new MessageBag($validationErrors);

        $mockValidator->expects($this->once())->method('fails')->willReturn(true);

        $mockValidator->expects(self::once())->method('errors')->willReturn($messageBag);

        $mockServiceFactory
            ->expects($this->once())
            ->method('getValidator')
            ->with(ProvisionProvider::MICROSOFT_ONLINE, $this->tenantIdRequest)
            ->willReturn($mockValidator);

        $provisionService = new Microsoft365ProvisionService(
            $mockServiceFactory,
            $this->createStub(MicrosoftOnlineService::class),
            $this->createStub(MicrosoftGraphService::class),
        );

        $validationResult = $provisionService->validate($this->tenantIdRequest);

        self::assertNotNull($validationResult);
        self::assertSame(ProvisionStatus::VALIDATION_ERROR, $validationResult->provisionStatus);
        self::assertNotNull($validationResult->validationResult);
        self::assertSame($validationErrors, $validationResult->validationResult->messages);
    }
}
