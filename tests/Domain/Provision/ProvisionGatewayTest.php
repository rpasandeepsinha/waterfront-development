<?php

declare(strict_types=1);

namespace Tests\Domain\Provision;

use Exception;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionErrorMessage;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Exceptions\StoreProvisionRequestException;
use Waterfront\Domain\Provision\Exceptions\StoreProvisionResultException;
use Waterfront\Domain\Provision\Factories\ProvisionServiceFactory;
use Waterfront\Domain\Provision\Interfaces\ProvisionContextRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionServiceInterface;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;
use Waterfront\Domain\Provision\Models\ProvisioningResult;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Domain\Provision\Repositories\ProvisioningResultRepository;
use Waterfront\Domain\Provision\Results\ProvisionResult;
use Waterfront\Domain\Provision\Services\ProvisionTraceabilityService;
use Waterfront\Domain\Provision\Validation\RetryRequestValidator;
use Waterfront\Domain\Provision\Validation\ValidationResult;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(ProvisionGateway::class)]
#[AllowMockObjectsWithoutExpectations]
class ProvisionGatewayTest extends TestCase
{
    private ProvisionTraceabilityService&MockObject $mockTraceablityService;

    private ProvisionServiceFactory&MockObject $mockServiceFactory;

    private ProvisionServiceInterface&MockObject $mockTypeSpecificService;

    private LoggerInterface&MockObject $mockLogger;

    private ProvisioningRequestRepository&MockObject $mockProvisioningRequestRepository;

    private RetryRequestValidator $retryRequestValidator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockTraceablityService = self::createMock(ProvisionTraceabilityService::class);
        $this->mockServiceFactory = $this->createMock(ProvisionServiceFactory::class);
        $this->mockTypeSpecificService = $this->createMock(ProvisionServiceInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockProvisioningRequestRepository = $this->createMock(ProvisioningRequestRepository::class);
        $this->retryRequestValidator = new RetryRequestValidator($this->mockProvisioningRequestRepository);
    }

    #[Test]
    public function serviceSend(): void
    {
        $mockProvisionRequest = self::createMockRequest(requiresValidation: true);
        $provisionResult = new ProvisionResult($mockProvisionRequest, ProvisionStatus::SUCCESS);
        $requestId = 1;

        $this->mockTypeSpecificService
            ->expects(self::once())
            ->method('getDefaultProvider')
            ->willReturn(ProvisionProvider::PLESK);

        $this->mockServiceFactory->expects(self::once())->method('create')->willReturn($this->mockTypeSpecificService);

        $this->mockTypeSpecificService
            ->expects(self::once())
            ->method('validate')
            ->with($mockProvisionRequest)
            ->willReturn(null);

        $this->mockTypeSpecificService
            ->expects(self::once())
            ->method('send')
            ->with($mockProvisionRequest)
            ->willReturn($provisionResult);

        $this->mockTraceablityService
            ->expects(self::once())
            ->method('storeRequest')
            ->with($mockProvisionRequest)
            ->willReturn($requestId);

        $this->mockTraceablityService->expects(self::once())->method('storeResult')->with($provisionResult, $requestId);

        $service = new ProvisionGateway(
            provisionServiceFactory: $this->mockServiceFactory,
            provisionTraceabilityService: $this->mockTraceablityService,
            repository: $this->createStub(ProvisioningResultRepository::class),
            logger: $this->mockLogger,
            retryRequestValidator: $this->retryRequestValidator,
        );
        $result = $service->request($mockProvisionRequest);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    #[Test]
    public function whenValidationFailsTypeServiceIsNotSend(): void
    {
        $requestId = 1;
        $validationResult = new ValidationResult(isValid: false, messages: ['key' => ['error' => 'failed']]);
        $exception = new Exception('Validation failed');
        $mockProvisionRequest = self::createMockRequest(requiresValidation: true);
        $mockProvisionRequest->requestId = $requestId;

        $mockResult = new ProvisionResult(
            provisionData: $mockProvisionRequest,
            provisionStatus: ProvisionStatus::VALIDATION_ERROR,
            exception: $exception,
            validationResult: $validationResult,
        );

        $this->mockServiceFactory->expects(self::once())->method('create')->willReturn($this->mockTypeSpecificService);

        $this->mockTypeSpecificService
            ->expects(self::once())
            ->method('getDefaultProvider')
            ->willReturn(ProvisionProvider::PLESK);

        $this->mockTypeSpecificService
            ->expects(self::once())
            ->method('validate')
            ->with($mockProvisionRequest)
            ->willReturn($mockResult);

        $this->mockTraceablityService
            ->expects(self::once())
            ->method('storeRequest')
            ->with($mockProvisionRequest)
            ->willReturn($requestId);

        $this->mockTraceablityService->expects(self::once())->method('storeResult')->with($mockResult, $requestId);

        $this->mockTraceablityService->expects(self::once())->method('storeResult');

        $this->mockTypeSpecificService->expects(self::never())->method('send');

        $service = new ProvisionGateway(
            provisionServiceFactory: $this->mockServiceFactory,
            provisionTraceabilityService: $this->mockTraceablityService,
            repository: $this->createStub(ProvisioningResultRepository::class),
            logger: $this->mockLogger,
            retryRequestValidator: $this->retryRequestValidator,
        );
        $result = $service->request($mockProvisionRequest);

        self::assertSame(ProvisionStatus::VALIDATION_ERROR, $result->provisionStatus);
        self::assertSame($validationResult, $result->validationResult);
        self::assertSame($exception, $result->exception);
    }

    #[Test]
    public function requestCanSkipValidation(): void
    {
        $requestId = 1;
        $mockProvisionRequest = self::createMockRequest();
        $mockProvisionRequest->requestId = 1;

        $this->mockTypeSpecificService
            ->expects(self::once())
            ->method('getDefaultProvider')
            ->willReturn(ProvisionProvider::PLESK);

        $this->mockServiceFactory->expects(self::once())->method('create')->willReturn($this->mockTypeSpecificService);

        $this->mockTypeSpecificService->expects(self::never())->method('validate');

        $this->mockTraceablityService
            ->expects(self::once())
            ->method('storeRequest')
            ->with($mockProvisionRequest)
            ->willReturn($requestId);

        $this->mockTraceablityService->expects(self::once())->method('storeResult');

        $this->mockTypeSpecificService
            ->expects(self::once())
            ->method('send')
            ->with($mockProvisionRequest)
            ->willReturn(new ProvisionResult($mockProvisionRequest, ProvisionStatus::SUCCESS));

        $service = new ProvisionGateway(
            provisionServiceFactory: $this->mockServiceFactory,
            provisionTraceabilityService: $this->mockTraceablityService,
            repository: $this->createStub(ProvisioningResultRepository::class),
            logger: $this->mockLogger,
            retryRequestValidator: $this->retryRequestValidator,
        );
        $result = $service->request($mockProvisionRequest);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    #[Test]
    public function retryValidationIsSkippedWhenRetryFieldsArePartiallySet(): void
    {
        $requestId = 1;
        $mockProvisionRequest = self::createMockRequest(retryOf: Uuid::uuid4());

        // Without both retryOf and retryRequester the request is not considered a retry,
        // so retry validation is skipped, and the regular gateway flow continues.
        $this->mockProvisioningRequestRepository->expects(self::never())->method('findByUuid');

        $this->mockTypeSpecificService
            ->expects(self::once())
            ->method('getDefaultProvider')
            ->willReturn(ProvisionProvider::PLESK);

        $this->mockServiceFactory->expects(self::once())->method('create')->willReturn($this->mockTypeSpecificService);

        $this->mockTraceablityService
            ->expects(self::once())
            ->method('storeRequest')
            ->with($mockProvisionRequest)
            ->willReturn($requestId);

        $this->mockTypeSpecificService
            ->expects(self::once())
            ->method('send')
            ->with($mockProvisionRequest)
            ->willReturn(new ProvisionResult($mockProvisionRequest, ProvisionStatus::SUCCESS));

        $this->mockTraceablityService->expects(self::once())->method('storeResult');

        $service = new ProvisionGateway(
            provisionServiceFactory: $this->mockServiceFactory,
            provisionTraceabilityService: $this->mockTraceablityService,
            repository: $this->createStub(ProvisioningResultRepository::class),
            logger: $this->mockLogger,
            retryRequestValidator: $this->retryRequestValidator,
        );

        $result = $service->request($mockProvisionRequest);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
    }

    #[Test]
    public function retryValidationFailsWhenRetryOriginDoesNotExist(): void
    {
        $mockProvisionRequest = self::createMockRequest(
            retryOf: Uuid::uuid4(),
            retryRequester: Uuid::uuid4(),
        );

        $this->mockProvisioningRequestRepository
            ->expects(self::once())
            ->method('findByUuid')
            ->with($mockProvisionRequest->retryOf)
            ->willReturn(null);

        $this->mockServiceFactory->expects(self::never())->method('create');

        $service = new ProvisionGateway(
            provisionServiceFactory: $this->mockServiceFactory,
            provisionTraceabilityService: $this->mockTraceablityService,
            repository: $this->createStub(ProvisioningResultRepository::class),
            logger: $this->mockLogger,
            retryRequestValidator: $this->retryRequestValidator,
        );

        $result = $service->request($mockProvisionRequest);

        self::assertSame(ProvisionStatus::VALIDATION_ERROR, $result->provisionStatus);
        self::assertSame(
            [ProvisionErrorMessage::RETRY_ORIGIN_NOT_FOUND->value],
            $result->validationResult?->messages['retryOf'] ?? null,
        );
    }

    #[Test]
    public function retryValidationFailsWhenRequestTypeDiffersFromOrigin(): void
    {
        $mockProvisionRequest = self::createMockRequest(
            retryOf: Uuid::uuid4(),
            retryRequester: Uuid::uuid4(),
            type: ProvisionType::HOSTING,
            name: ProvisionRequestName::CREATE_HOSTING,
        );

        $originRequest = new ProvisioningRequest();
        $originRequest->request_type = ProvisionType::BACKUP;
        $originRequest->request_name = ProvisionRequestName::CREATE_HOSTING;
        $this->attachFailedResult($originRequest);

        $this->mockProvisioningRequestRepository
            ->expects(self::once())
            ->method('findByUuid')
            ->with($mockProvisionRequest->retryOf)
            ->willReturn($originRequest);

        $service = new ProvisionGateway(
            provisionServiceFactory: $this->mockServiceFactory,
            provisionTraceabilityService: $this->mockTraceablityService,
            repository: $this->createStub(ProvisioningResultRepository::class),
            logger: $this->mockLogger,
            retryRequestValidator: $this->retryRequestValidator,
        );

        $result = $service->request($mockProvisionRequest);

        self::assertSame(ProvisionStatus::VALIDATION_ERROR, $result->provisionStatus);
        self::assertSame(
            [ProvisionErrorMessage::RETRY_ORIGIN_TYPE_MISMATCH->value],
            $result->validationResult?->messages['retryOf'] ?? null,
        );
    }

    #[Test]
    public function retryValidationFailsWhenRequestNameDiffersFromOrigin(): void
    {
        $mockProvisionRequest = self::createMockRequest(
            retryOf: Uuid::uuid4(),
            retryRequester: Uuid::uuid4(),
            type: ProvisionType::HOSTING,
            name: ProvisionRequestName::CREATE_HOSTING,
        );

        $originRequest = new ProvisioningRequest();
        $originRequest->request_type = ProvisionType::HOSTING;
        $originRequest->request_name = ProvisionRequestName::GET_HOSTING_SSO;
        $this->attachFailedResult($originRequest);

        $this->mockProvisioningRequestRepository
            ->expects(self::once())
            ->method('findByUuid')
            ->with($mockProvisionRequest->retryOf)
            ->willReturn($originRequest);

        $service = new ProvisionGateway(
            provisionServiceFactory: $this->mockServiceFactory,
            provisionTraceabilityService: $this->mockTraceablityService,
            repository: $this->createStub(ProvisioningResultRepository::class),
            logger: $this->mockLogger,
            retryRequestValidator: $this->retryRequestValidator,
        );

        $result = $service->request($mockProvisionRequest);

        self::assertSame(ProvisionStatus::VALIDATION_ERROR, $result->provisionStatus);
        self::assertSame(
            [ProvisionErrorMessage::RETRY_ORIGIN_NAME_MISMATCH->value],
            $result->validationResult?->messages['retryOf'] ?? null,
        );
    }

    #[Test]
    public function retryValidationFailsWhenOriginRequestIsAlreadyARetry(): void
    {
        $mockProvisionRequest = self::createMockRequest(
            retryOf: Uuid::uuid4(),
            retryRequester: Uuid::uuid4(),
            type: ProvisionType::HOSTING,
            name: ProvisionRequestName::CREATE_HOSTING,
        );

        $originRequest = new ProvisioningRequest();
        $originRequest->request_type = ProvisionType::HOSTING;
        $originRequest->request_name = ProvisionRequestName::CREATE_HOSTING;
        $originRequest->retry_of_request_id = 1;
        $this->attachFailedResult($originRequest);

        $this->mockProvisioningRequestRepository
            ->expects(self::once())
            ->method('findByUuid')
            ->with($mockProvisionRequest->retryOf)
            ->willReturn($originRequest);

        $service = new ProvisionGateway(
            provisionServiceFactory: $this->mockServiceFactory,
            provisionTraceabilityService: $this->mockTraceablityService,
            repository: $this->createStub(ProvisioningResultRepository::class),
            logger: $this->mockLogger,
            retryRequestValidator: $this->retryRequestValidator,
        );

        $result = $service->request($mockProvisionRequest);

        self::assertSame(ProvisionStatus::VALIDATION_ERROR, $result->provisionStatus);
        self::assertSame(
            [ProvisionErrorMessage::RETRY_ORIGIN_IS_RETRY->value],
            $result->validationResult?->messages['retryOf'] ?? null,
        );
    }

    #[Test]
    public function validRetryRequestWillContinueRegularGatewayFlow(): void
    {
        $requestId = 1;
        $mockProvisionRequest = self::createMockRequest(
            retryOf: Uuid::uuid4(),
            retryRequester: Uuid::uuid4(),
            type: ProvisionType::HOSTING,
            name: ProvisionRequestName::CREATE_HOSTING,
            requiresValidation: false,
        );
        $provisionResult = new ProvisionResult($mockProvisionRequest, ProvisionStatus::SUCCESS);

        $originRequest = new ProvisioningRequest();
        $originRequest->request_type = ProvisionType::HOSTING;
        $originRequest->request_name = ProvisionRequestName::CREATE_HOSTING;
        $originRequest->retry_of_request_id = null;
        $this->attachFailedResult($originRequest);

        $this->mockProvisioningRequestRepository
            ->expects(self::once())
            ->method('findByUuid')
            ->with($mockProvisionRequest->retryOf)
            ->willReturn($originRequest);

        $this->mockTypeSpecificService
            ->expects(self::once())
            ->method('getDefaultProvider')
            ->willReturn(ProvisionProvider::PLESK);

        $this->mockServiceFactory->expects(self::once())->method('create')->willReturn($this->mockTypeSpecificService);

        $this->mockTypeSpecificService
            ->expects(self::once())
            ->method('send')
            ->with($mockProvisionRequest)
            ->willReturn($provisionResult);

        $this->mockTraceablityService
            ->expects(self::once())
            ->method('storeRequest')
            ->with($mockProvisionRequest)
            ->willReturn($requestId);

        $this->mockTraceablityService->expects(self::once())->method('storeResult')->with($provisionResult, $requestId);

        $service = new ProvisionGateway(
            provisionServiceFactory: $this->mockServiceFactory,
            provisionTraceabilityService: $this->mockTraceablityService,
            repository: $this->createStub(ProvisioningResultRepository::class),
            logger: $this->mockLogger,
            retryRequestValidator: $this->retryRequestValidator,
        );

        $result = $service->request($mockProvisionRequest);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
    }

    #[Test]
    public function exceptionDuringRequestReturnsResultWithExceptionNotStored(): void
    {
        $exception = new Exception('Something went wrong');
        $mockProvisionRequest = self::createMockRequest();

        $this->mockServiceFactory
            ->expects(self::once())
            ->method('create')
            ->with(ProvisionType::HOSTING)
            ->willThrowException($exception);

        $service = new ProvisionGateway(
            provisionServiceFactory: $this->mockServiceFactory,
            provisionTraceabilityService: $this->mockTraceablityService,
            repository: $this->createStub(ProvisioningResultRepository::class),
            logger: $this->mockLogger,
            retryRequestValidator: $this->retryRequestValidator,
        );

        $this->mockLogger
            ->expects(self::once())
            ->method('error')
            ->with(
                sprintf('Provisioning failed internally: %s', $exception->getMessage()),
                self::callback(
                    fn (array $context) => (
                        $context['exception'] instanceof Exception
                        && $context[LoggingContextKeys::PROVISIONING_REQUEST_ID] === 0
                    ),
                ),
            );

        $result = $service->request($mockProvisionRequest);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($exception, $result->exception);
    }

    #[Test]
    public function retryValidationFailsWhenOriginRequestDidNotFail(): void
    {
        $mockProvisionRequest = self::createMockRequest(
            retryOf: Uuid::uuid4(),
            retryRequester: Uuid::uuid4(),
            type: ProvisionType::HOSTING,
            name: ProvisionRequestName::CREATE_HOSTING,
        );

        $originRequest = new ProvisioningRequest();
        $originRequest->request_type = ProvisionType::HOSTING;
        $originRequest->request_name = ProvisionRequestName::CREATE_HOSTING;
        $originRequest->retry_of_request_id = null;

        $originResult = new ProvisioningResult();
        $originResult->status = ProvisionStatus::SUCCESS;
        $originRequest->setRelation('result', $originResult);

        $this->mockProvisioningRequestRepository
            ->expects(self::once())
            ->method('findByUuid')
            ->with($mockProvisionRequest->retryOf)
            ->willReturn($originRequest);

        $this->mockServiceFactory->expects(self::never())->method('create');

        $service = new ProvisionGateway(
            provisionServiceFactory: $this->mockServiceFactory,
            provisionTraceabilityService: $this->mockTraceablityService,
            repository: $this->createStub(ProvisioningResultRepository::class),
            logger: $this->mockLogger,
            retryRequestValidator: $this->retryRequestValidator,
        );

        $result = $service->request($mockProvisionRequest);

        self::assertSame(ProvisionStatus::VALIDATION_ERROR, $result->provisionStatus);
        self::assertSame(
            [ProvisionErrorMessage::RETRY_ORIGIN_NOT_FAILED->value],
            $result->validationResult?->messages['retryOf'] ?? null,
        );
    }

    #[Test]
    public function exceptionWithRequestWillStillBeStoredIfWeHaveARequestId(): void
    {
        $exception = new Exception('Something went wrong');
        $requestId = 1;
        $mockProvisionRequest = self::createMockRequest();
        $mockProvisionRequest->requestId = 1;

        $this->mockServiceFactory
            ->expects(self::once())
            ->method('create')
            ->with(ProvisionType::HOSTING)
            ->willThrowException($exception);

        $this->mockTraceablityService
            ->expects(self::once())
            ->method('storeResult')
            ->with(
                self::callback(
                    fn (ProvisionResult $result) => (
                        $result->provisionStatus === ProvisionStatus::FAILED
                        && $result->exception === $exception
                    ),
                ),
                $requestId,
            );

        $service = new ProvisionGateway(
            provisionServiceFactory: $this->mockServiceFactory,
            provisionTraceabilityService: $this->mockTraceablityService,
            repository: $this->createStub(ProvisioningResultRepository::class),
            logger: $this->mockLogger,
            retryRequestValidator: $this->retryRequestValidator,
        );

        $this->mockLogger
            ->expects(self::once())
            ->method('error')
            ->with(
                sprintf('Provisioning failed internally: %s', $exception->getMessage()),
                self::callback(
                    fn (array $context) => (
                        $context['exception'] instanceof Exception
                        && $context[LoggingContextKeys::PROVISIONING_REQUEST_ID] === $requestId
                    ),
                ),
            );

        $result = $service->request($mockProvisionRequest);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($exception, $result->exception);
    }

    #[Test]
    public function storingRequestExceptionDuringRequestStillReturnsResultButDoesNotStoreAgain(): void
    {
        $storeException = new Exception('Something went wrong storing request');
        $requestName = ProvisionRequestName::CREATE_HOSTING;
        $tag = Uuid::uuid4();
        $context = Uuid::uuid4();
        $mockProvisionRequest = self::createMockRequest(
            tag: $tag,
            context: $context,
            name: $requestName,
        );

        $this->mockServiceFactory->expects(self::once())->method('create')->willReturn($this->mockTypeSpecificService);

        $this->mockTypeSpecificService
            ->expects(self::once())
            ->method('getDefaultProvider')
            ->willReturn(ProvisionProvider::PLESK);

        $this->mockTraceablityService
            ->expects(self::once())
            ->method('storeRequest')
            ->willThrowException($storeException);

        $service = new ProvisionGateway(
            provisionServiceFactory: $this->mockServiceFactory,
            provisionTraceabilityService: $this->mockTraceablityService,
            repository: $this->createStub(ProvisioningResultRepository::class),
            logger: $this->mockLogger,
            retryRequestValidator: $this->retryRequestValidator,
        );

        $expectedErrorMessage = sprintf(
            'Unable to store provision request [%s] in the database. Context: [%s] Tag: [%s]',
            $requestName->value,
            $context->toString(),
            $tag->toString(),
        );

        $this->mockLogger
            ->expects(self::once())
            ->method('error')
            ->with(
                sprintf('Provisioning failed internally: %s', $expectedErrorMessage),
                self::callback(
                    fn (array $context) => (
                        $context['exception'] instanceof StoreProvisionRequestException
                        && $context[LoggingContextKeys::PROVISIONING_REQUEST_ID] === 0
                    ),
                ),
            );

        $result = $service->request($mockProvisionRequest);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(StoreProvisionRequestException::class, $result->exception);
        self::assertSame($storeException, $result->exception->getPrevious());

        self::assertSame($expectedErrorMessage, $result->exception->getMessage());
    }

    #[Test]
    public function storingResultExceptionDuringValidationFailStillReturnsResultButDoesNotStoreAgain(): void
    {
        $requestId = 1;
        $provider = ProvisionProvider::PLESK;
        $exception = new Exception('something went wrong when storing result');
        $mockProvisionRequest =
            self::createMockRequest(
                requiresValidation: true,
            );
        $mockProvisionRequest->requestId = $requestId;

        $this->mockTypeSpecificService->expects(self::once())->method('getDefaultProvider')->willReturn($provider);

        $validationFailedResult = new ProvisionResult(
            $this->createStub(ProvisionRequestInterface::class),
            ProvisionStatus::FAILED,
        );

        $this->mockTypeSpecificService
            ->expects(self::once())
            ->method('validate')
            ->with($mockProvisionRequest)
            ->willReturn($validationFailedResult);

        $this->mockServiceFactory->expects(self::once())->method('create')->willReturn($this->mockTypeSpecificService);

        $this->mockTraceablityService
            ->expects(self::once())
            ->method('storeRequest')
            ->with($mockProvisionRequest, $provider)
            ->willReturn($requestId);

        $this->mockTraceablityService->expects(self::once())->method('storeResult')->willThrowException($exception);

        $expectedErrorMessage = sprintf(
            'Unable to store provision result in the database from request [%d]. Provision Status: %s',
            $requestId,
            $validationFailedResult->provisionStatus->value,
        );

        $this->mockLogger
            ->expects(self::once())
            ->method('error')
            ->with(
                sprintf('Provisioning failed internally: %s', $expectedErrorMessage),
                self::callback(
                    fn (array $context) => (
                        $context['exception'] instanceof StoreProvisionResultException
                        && $context[LoggingContextKeys::PROVISIONING_REQUEST_ID] === $requestId
                    ),
                ),
            );

        $service = new ProvisionGateway(
            provisionServiceFactory: $this->mockServiceFactory,
            provisionTraceabilityService: $this->mockTraceablityService,
            repository: $this->createStub(ProvisioningResultRepository::class),
            logger: $this->mockLogger,
            retryRequestValidator: $this->retryRequestValidator,
        );
        $result = $service->request($mockProvisionRequest);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(StoreProvisionResultException::class, $result->exception);
        self::assertSame($exception, $result->exception->getPrevious());
        self::assertSame($expectedErrorMessage, $result->exception->getMessage());
    }

    #[Test]
    public function storingResultExceptionAfterSendFailStillReturnsResultButDoesNotStoreAgain(): void
    {
        $requestId = 1;
        $provider = ProvisionProvider::PLESK;
        $exception = new Exception('something went wrong when storing result');

        $mockProvisionRequest = self::createMockRequest();
        $mockProvisionRequest->requestId = $requestId;

        $provisionResult = new ProvisionResult($mockProvisionRequest, ProvisionStatus::SUCCESS);

        $this->mockTypeSpecificService->expects(self::once())->method('getDefaultProvider')->willReturn($provider);

        $this->mockServiceFactory->expects(self::once())->method('create')->willReturn($this->mockTypeSpecificService);

        $this->mockTraceablityService
            ->expects(self::once())
            ->method('storeRequest')
            ->with($mockProvisionRequest, $provider)
            ->willReturn($requestId);

        $this->mockTypeSpecificService
            ->expects(self::once())
            ->method('send')
            ->with($mockProvisionRequest)
            ->willReturn($provisionResult);

        $this->mockTraceablityService->expects(self::once())->method('storeResult')->willThrowException($exception);

        $expectedErrorMessage = sprintf(
            'Unable to store provision result in the database from request [%d]. Provision Status: %s',
            $requestId,
            $provisionResult->provisionStatus->value,
        );

        $this->mockLogger
            ->expects(self::once())
            ->method('error')
            ->with(
                sprintf('Provisioning failed internally: %s', $expectedErrorMessage),
                self::callback(
                    fn (array $context) => (
                        $context['exception'] instanceof StoreProvisionResultException
                        && $context[LoggingContextKeys::PROVISIONING_REQUEST_ID] === $requestId
                    ),
                ),
            );

        $service = new ProvisionGateway(
            provisionServiceFactory: $this->mockServiceFactory,
            provisionTraceabilityService: $this->mockTraceablityService,
            repository: $this->createStub(ProvisioningResultRepository::class),
            logger: $this->mockLogger,
            retryRequestValidator: $this->retryRequestValidator,
        );
        $result = $service->request($mockProvisionRequest);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(StoreProvisionResultException::class, $result->exception);
        self::assertSame($exception, $result->exception->getPrevious());
        self::assertSame($expectedErrorMessage, $result->exception->getMessage());
    }

    /**
     * This method can be used to easily create a stub instance of a
     * ProvisionRequestInterface that can be setup with arguments
     * to customize the request as needed for the specific test.
     *
     * @return ($context is null ? ProvisionRequestInterface : ProvisionContextRequestInterface)
     */
    public static function createMockRequest(
        ?UuidInterface $tag = null,
        ?UuidInterface $context = null,
        ?UuidInterface $retryOf = null,
        ?UuidInterface $retryRequester = null,
        ?ProvisionProvider $provider = null,
        ?ProvisionType $type = null,
        ?ProvisionRequestName $name = null,
        bool $requiresValidation = false,
    ): ProvisionRequestInterface|ProvisionContextRequestInterface {
        $tag ??= Uuid::uuid4();

        $type ??= ProvisionType::cases()[0];

        $name ??= ProvisionRequestName::cases()[0];

        if ($context instanceof UuidInterface) {
            return new class(
                $context,
                $tag,
                $retryOf,
                $retryRequester,
                $provider,
                $type,
                $name,
                $requiresValidation,
            ) implements ProvisionRequestInterface, ProvisionContextRequestInterface {
                public int $requestId = 0;

                public function __construct(
                    public protected(set) UuidInterface $context,
                    public UuidInterface $tag,
                    public ?UuidInterface $retryOf,
                    public ?UuidInterface $retryRequester,
                    public ?ProvisionProvider $provider,
                    public protected(set) ProvisionType $type,
                    public ProvisionRequestName $name,
                    public protected(set) bool $requiresValidation,
                ) {
                }

                public function isRetry(): bool
                {
                    return $this->retryOf instanceof UuidInterface && $this->retryRequester instanceof UuidInterface;
                }

                public function defaultLogContext(): array
                {
                    return [
                        LoggingContextKeys::PROVISIONING_TYPE => $this->type,
                        LoggingContextKeys::PROVISIONING_PROVIDER => $this->provider,
                        LoggingContextKeys::PROVISIONING_REQUEST_ID => $this->requestId,
                        LoggingContextKeys::PROVISIONING_CONTEXT => $this->context,
                    ];
                }
            };
        }

        return new class($tag, $retryOf, $retryRequester, $provider, $type, $name, $requiresValidation) implements
            ProvisionRequestInterface {
            public int $requestId = 0;

            public function __construct(
                public UuidInterface $tag,
                public ?UuidInterface $retryOf,
                public ?UuidInterface $retryRequester,
                public ?ProvisionProvider $provider,
                public protected(set) ProvisionType $type,
                public ProvisionRequestName $name,
                public protected(set) bool $requiresValidation,
            ) {
            }

            public function isRetry(): bool
            {
                return $this->retryOf instanceof UuidInterface && $this->retryRequester instanceof UuidInterface;
            }

            public function defaultLogContext(): array
            {
                return [
                    LoggingContextKeys::PROVISIONING_TYPE => $this->type,
                    LoggingContextKeys::PROVISIONING_PROVIDER => $this->provider,
                    LoggingContextKeys::PROVISIONING_REQUEST_ID => $this->requestId,
                ];
            }
        };
    }

    private function attachFailedResult(ProvisioningRequest $request): void
    {
        $result = new ProvisioningResult();
        $result->status = ProvisionStatus::FAILED;
        $request->setRelation('result', $result);
    }
}
