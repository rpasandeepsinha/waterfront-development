<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Services;

use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use ReflectionClassConstant;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Tests\Domain\Provision\Stubs\ProvisionRequestWithMaskedPropsProvision;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\TestCase;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Factories\ProvisionSerializeFactory;
use Waterfront\Domain\Provision\Interfaces\ProvisionContextRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365TenantIdRequest;
use Waterfront\Domain\Provision\Microsoft365\Results\TenantIdResult;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;
use Waterfront\Domain\Provision\Models\ProvisioningResult;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Domain\Provision\Services\ProvisionTraceabilityService;
use Waterfront\Domain\Provision\Validation\ValidationResult;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

#[CoversClass(ProvisionTraceabilityService::class)]
class ProvisionTraceabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProvisionTraceabilityService $service;

    private UuidInterface $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = Str::uuid();
        $this->service = $this->app->make(ProvisionTraceabilityService::class);
    }

    #[Test]
    public function storeRequest(): void
    {
        $request = new Microsoft365TenantIdRequest('bla.com', $this->context);
        $request->tag = Uuid::uuid4();
        $request->provider = ProvisionProvider::MICROSOFT_ONLINE;
        $serializedRequest = sprintf(
            '{"tenantName": "%s"}',
            $request->tenantName,
        );

        $resultId = $this->service->storeRequest($request, ProvisionProvider::MICROSOFT_ONLINE);

        self::assertDatabaseHas(ProvisioningRequest::class, [
            'id' => $resultId,
            'request_name' => $request->name,
            'request_data' => $serializedRequest,
            'context_uuid' => $this->context,
            'provision_provider' => $request->provider,
        ]);
    }

    #[Test]
    public function storeRequestWithMaskedProperties(): void
    {
        $user = 'testuser';
        $password = 'testpassword';
        $secretInt = 1337;
        $secretFloat = 13.37;
        $secretArray = ['key' => 'value'];

        $request = new ProvisionRequestWithMaskedPropsProvision(
            username: $user,
            password: $password,
            secretInt: $secretInt,
            secretFloat: $secretFloat,
            secretArray: $secretArray,
            context: $this->context,
        );

        $request->tag = Uuid::uuid4();
        $request->provider = ProvisionProvider::MICROSOFT_ONLINE;
        $serializedRequest = sprintf(
            '{"password": "%s", "username": "%s", "secretInt": %d, "secretArray": null, "secretFloat": %f}',
            '****',
            $request->username,
            0,
            0.0,
        );

        $resultId = $this->service->storeRequest($request, ProvisionProvider::MICROSOFT_ONLINE);

        self::assertDatabaseHas(ProvisioningRequest::class, [
            'id' => $resultId,
            'request_name' => $request->name,
            'request_data' => $serializedRequest,
            'context_uuid' => $this->context,
            'provision_provider' => $request->provider,
        ]);
    }

    #[Test]
    public function storeRequestDoesNotContainDatabaseColumns(): void
    {
        $context = Uuid::uuid4();
        $tag = Uuid::uuid4();
        $provider = ProvisionProvider::PLESK;
        $type = ProvisionType::HOSTING;
        $name = ProvisionRequestName::CREATE_HOSTING;
        $requiresValidation = true;
        $retryOf = null;
        $retryRequester = null;

        $request = new class(
            $context,
            $tag,
            $retryOf,
            $retryRequester,
            $provider,
            $type,
            $name,
            $requiresValidation,
        ) implements ProvisionRequestInterface, ProvisionContextRequestInterface {
            public int $requestId;

            public function __construct(
                public protected(set) UuidInterface $context,
                public UuidInterface $tag,
                public ?UuidInterface $retryOf,
                public ?UuidInterface $retryRequester,
                public ?ProvisionProvider $provider,
                public protected(set) ProvisionType $type,
                public ProvisionRequestName $name,
                public protected(set) bool $requiresValidation,
                public string $hostingData = 'data',
                public string $moreData = 'actual-provision-request-data',
            ) {
            }

            public function isRetry(): bool
            {
                return $this->retryOf instanceof UuidInterface && $this->retryRequester instanceof UuidInterface;
            }

            public function defaultLogContext(): array
            {
                return [];
            }
        };

        Assert::notNull($request->provider);
        $resultId = $this->service->storeRequest($request, $request->provider);

        $storedRequest = ProvisioningRequest::findOrFail($resultId);

        /** @var array<mixed> $requestData */
        $requestData = json_decode($storedRequest->request_data, true, 512, JSON_THROW_ON_ERROR);

        /** @var string[] $databaseColumns */
        $databaseColumns = new ReflectionClassConstant(
            class: ProvisionTraceabilityService::class,
            constant: 'DATABASE_COLUMNS',
        )->getValue();

        foreach ($databaseColumns as $column) {
            self::assertArrayNotHasKey(
                $column,
                $requestData,
                sprintf(
                    'The key "%s" should not be present in the stored request data as it is a dedicated database column.',
                    $column,
                ),
            );
        }

        // Our request has specific request data, so the database should contain only this data in the request_data.
        self::assertSame(
            [
                'moreData' => 'actual-provision-request-data',
                'hostingData' => 'data',
            ],
            json_decode($storedRequest->request_data, true),
        );
    }

    #[Test]
    public function storeResult(): void
    {
        $request = new Microsoft365TenantIdRequest('bla.com', $this->context);
        $requestModelId = new ProvisioningRequestFactory()->m365()->createOne()->id;
        $expectedStatus = ProvisionStatus::VALIDATION_ERROR;
        $previousException = new Exception('previous error', 1338);
        $expectedException = new Exception('error', 1337, $previousException);
        $expectedValidationMessages = [
            'key' => [
                'error',
                'more errors',
            ],
        ];

        $validationResult = new ValidationResult(false, $expectedValidationMessages);

        $result = new TenantIdResult(
            provisionData: $request,
            provisionStatus: $expectedStatus,
            exception: $expectedException,
            validationResult: $validationResult,
        );

        $this->service->storeResult($result, $requestModelId);

        self::assertDatabaseHas(ProvisioningResult::class, [
            'request_id' => $requestModelId,
            'status' => $expectedStatus,
        ]);

        $storedResult = ProvisioningResult::query()->where('request_id', $requestModelId)->firstOrFail();
        $response = json_decode($storedResult->response, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($response);
        /** @var array{provisionStatus: string, validationResult: array<mixed>, exception: array<mixed>} $response */
        self::assertSame($expectedStatus->value, $response['provisionStatus']);
        self::assertSame(
            ['isValid' => false, 'messages' => $expectedValidationMessages],
            $response['validationResult'],
        );

        self::assertSame($expectedException->getCode(), $response['exception']['code']);
        self::assertSame($expectedException->getFile(), $response['exception']['file']);
        self::assertSame($expectedException->getLine(), $response['exception']['line']);
        self::assertSame($expectedException::class, $response['exception']['class']);
        self::assertSame($expectedException->getMessage(), $response['exception']['message']);

        self::assertIsArray($response['exception']['previous']);
        self::assertSame($previousException->getCode(), $response['exception']['previous']['code']);
        self::assertSame($previousException->getFile(), $response['exception']['previous']['file']);
        self::assertSame($previousException->getLine(), $response['exception']['previous']['line']);
        self::assertSame($previousException::class, $response['exception']['previous']['class']);
        self::assertSame($previousException->getMessage(), $response['exception']['previous']['message']);
    }

    #[Test]
    public function storeResultException(): void
    {
        $mockLogger = self::createMock(LoggerInterface::class);
        $mockSerializeFactory = self::createMock(ProvisionSerializeFactory::class);

        $provisionTracebilityService = new ProvisionTraceabilityService(
            serializerFactory: $mockSerializeFactory,
            logger: $mockLogger,
            provisioningRequestRepository: self::createStub(ProvisioningRequestRepository::class),
        );

        $exception = new NotNormalizableValueException();

        $mockSerializeFactory->expects(self::once())->method('get')->willThrowException($exception);

        $request = new Microsoft365TenantIdRequest('bla.com', $this->context);
        $requestModelId = new ProvisioningRequestFactory()->m365()->createOne()->id;
        $expectedStatus = ProvisionStatus::SUCCESS;

        $result = new TenantIdResult($request, $expectedStatus);

        $mockLogger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Error occurred when normalizing provision result, unable to store request',
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_ONLINE,
                    LoggingContextKeys::PROVISIONING_REQUEST_ID => 0,
                    LoggingContextKeys::PROVISIONING_CONTEXT => $this->context,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

        $provisionTracebilityService->storeResult($result, $requestModelId);

        self::assertDatabaseEmpty(ProvisioningResult::class);
    }

    #[Test]
    public function storeRequestWithRetryDataWillStoreRetryRelation(): void
    {
        $originRequest = ProvisioningRequestFactory::new()->hosting()->createOne();
        $retryRequester = Uuid::uuid4();

        $request = new Microsoft365TenantIdRequest('bla.com', $this->context);
        $request->tag = Uuid::uuid4();
        $request->provider = ProvisionProvider::MICROSOFT_ONLINE;
        $request->retryOf = $originRequest->uuid;
        $request->retryRequester = $retryRequester;

        $resultId = $this->service->storeRequest($request, ProvisionProvider::MICROSOFT_ONLINE);

        self::assertDatabaseHas(ProvisioningRequest::class, [
            'id' => $resultId,
            'retry_of_request_id' => $originRequest->id,
            'requested_by_uuid' => $retryRequester,
        ]);
    }
}
