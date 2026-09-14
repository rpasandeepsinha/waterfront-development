<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Backup\Requests\GetBackupUsageRequest;
use Waterfront\Domain\Provision\Enums\ProvisionErrorMessage;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;
use Waterfront\Domain\Provision\Models\ProvisioningResult;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Domain\Provision\Validation\RetryRequestValidator;

#[CoversClass(RetryRequestValidator::class)]
class RetryRequestValidatorTest extends TestCase
{
    private ProvisioningRequestRepository&MockObject $requestRepository;

    private RetryRequestValidator $retryRequestValidator;

    private UuidInterface $retryOfUuid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requestRepository = $this->createMock(ProvisioningRequestRepository::class);
        $this->retryRequestValidator = new RetryRequestValidator($this->requestRepository);
        $this->retryOfUuid = Uuid::uuid4();
    }

    #[Test]
    public function validateReturnsNullWhenRequestIsNotARetry(): void
    {
        $request = new GetBackupUsageRequest(tagUuid: Uuid::uuid4());

        $this->requestRepository->expects(self::never())->method('findByUuid');

        self::assertNull($this->retryRequestValidator->validate($request));
    }

    #[Test]
    public function validateReturnsValidationErrorWhenOriginRequestIsNotFound(): void
    {
        $request = $this->createRetryRequest();

        $this->requestRepository
            ->expects(self::once())
            ->method('findByUuid')
            ->with($this->retryOfUuid)
            ->willReturn(null);

        $result = $this->retryRequestValidator->validate($request);

        self::assertNotNull($result);
        self::assertNotNull($result->validationResult);
        self::assertSame(
            [ProvisionErrorMessage::RETRY_ORIGIN_NOT_FOUND->value],
            $result->validationResult->messages['retryOf'],
        );
    }

    #[Test]
    public function validateReturnsValidationErrorWhenOriginRequestTypeDiffers(): void
    {
        $request = $this->createRetryRequest();

        $originRequest = $this->createOriginRequest(
            type: ProvisionType::HOSTING,
            name: $request->name,
            retryOfRequestId: null,
        );

        $this->requestRepository
            ->expects(self::once())
            ->method('findByUuid')
            ->with($this->retryOfUuid)
            ->willReturn($originRequest);

        $result = $this->retryRequestValidator->validate($request);

        self::assertNotNull($result);
        self::assertNotNull($result->validationResult);
        self::assertSame(
            [ProvisionErrorMessage::RETRY_ORIGIN_TYPE_MISMATCH->value],
            $result->validationResult->messages['retryOf'],
        );
    }

    #[Test]
    public function validateReturnsValidationErrorWhenOriginRequestNameDiffers(): void
    {
        $request = $this->createRetryRequest();

        $originRequest = $this->createOriginRequest(
            type: $request->type,
            name: ProvisionRequestName::CREATE_BACKUP,
            retryOfRequestId: null,
        );

        $this->requestRepository->expects(self::once())->method('findByUuid')->willReturn($originRequest);

        $result = $this->retryRequestValidator->validate($request);

        self::assertNotNull($result);
        self::assertNotNull($result->validationResult);
        self::assertSame(
            [ProvisionErrorMessage::RETRY_ORIGIN_NAME_MISMATCH->value],
            $result->validationResult->messages['retryOf'],
        );
    }

    #[Test]
    public function validateReturnsValidationErrorWhenOriginRequestIsItselfARetry(): void
    {
        $request = $this->createRetryRequest();

        $originRequest = $this->createOriginRequest(
            type: $request->type,
            name: $request->name,
            retryOfRequestId: 42,
        );

        $this->requestRepository->expects(self::once())->method('findByUuid')->willReturn($originRequest);

        $result = $this->retryRequestValidator->validate($request);

        self::assertNotNull($result);
        self::assertNotNull($result->validationResult);
        self::assertSame(
            [ProvisionErrorMessage::RETRY_ORIGIN_IS_RETRY->value],
            $result->validationResult->messages['retryOf'],
        );
    }

    #[Test]
    public function validateAccumulatesMultipleErrorsForRetryOfField(): void
    {
        $request = $this->createRetryRequest();

        $originRequest = $this->createOriginRequest(
            type: ProvisionType::HOSTING,
            name: ProvisionRequestName::CREATE_BACKUP,
            retryOfRequestId: 99,
            status: ProvisionStatus::SUCCESS,
        );

        $this->requestRepository->expects(self::once())->method('findByUuid')->willReturn($originRequest);

        $result = $this->retryRequestValidator->validate($request);

        self::assertNotNull($result);
        self::assertNotNull($result->validationResult);
        self::assertSame(
            [
                ProvisionErrorMessage::RETRY_ORIGIN_TYPE_MISMATCH->value,
                ProvisionErrorMessage::RETRY_ORIGIN_NAME_MISMATCH->value,
                ProvisionErrorMessage::RETRY_ORIGIN_IS_RETRY->value,
                ProvisionErrorMessage::RETRY_ORIGIN_NOT_FAILED->value,
            ],
            $result->validationResult->messages['retryOf'],
        );
    }

    #[Test]
    #[DataProvider('failedStatusProvider')]
    public function validateReturnsNullWhenRetryRequestIsValid(ProvisionStatus $status): void
    {
        $request = $this->createRetryRequest();

        $originRequest = $this->createOriginRequest(
            type: $request->type,
            name: $request->name,
            retryOfRequestId: null,
            status: $status,
        );

        $this->requestRepository->expects(self::once())->method('findByUuid')->willReturn($originRequest);

        self::assertNull($this->retryRequestValidator->validate($request));
    }

    #[Test]
    public function validateReturnsValidationErrorWhenOriginStatusIsNotFailed(): void
    {
        $request = $this->createRetryRequest();
        $originRequest = $this->createOriginRequest(
            type: $request->type,
            name: $request->name,
            retryOfRequestId: null,
            status: ProvisionStatus::SUCCESS,
        );

        $this->requestRepository->expects(self::once())->method('findByUuid')->willReturn($originRequest);

        $result = $this->retryRequestValidator->validate($request);

        self::assertNotNull($result);
        self::assertNotNull($result->validationResult);
        self::assertSame(
            [ProvisionErrorMessage::RETRY_ORIGIN_NOT_FAILED->value],
            $result->validationResult->messages['retryOf'],
        );
    }

    #[Test]
    public function validateReturnsValidationErrorWhenOriginDoesNotHaveAResult(): void
    {
        $request = $this->createRetryRequest();
        $originRequest = $this->createOriginRequest(
            type: $request->type,
            name: $request->name,
            retryOfRequestId: null,
        );
        $originRequest->setRelation('result', null);

        $this->requestRepository->expects(self::once())->method('findByUuid')->willReturn($originRequest);

        $result = $this->retryRequestValidator->validate($request);

        self::assertNotNull($result);
        self::assertNotNull($result->validationResult);
        self::assertSame(
            [ProvisionErrorMessage::RETRY_ORIGIN_NOT_FAILED->value],
            $result->validationResult->messages['retryOf'],
        );
    }

    /**
     * @return array<string, array{ProvisionStatus}>
     */
    public static function failedStatusProvider(): array
    {
        $statuses = [];

        foreach (ProvisionStatus::getFailedStatuses() as $status) {
            $statuses[$status->value] = [$status];
        }

        return $statuses;
    }

    private function createRetryRequest(): GetBackupUsageRequest
    {
        $request = new GetBackupUsageRequest(tagUuid: Uuid::uuid4());
        $request->retryOf = $this->retryOfUuid;
        $request->retryRequester = Uuid::uuid4();

        return $request;
    }

    private function createOriginRequest(
        ProvisionType $type,
        ProvisionRequestName $name,
        ?int $retryOfRequestId,
        ProvisionStatus $status = ProvisionStatus::FAILED,
    ): ProvisioningRequest {
        $originRequest = new ProvisioningRequest();
        $originRequest->request_type = $type;
        $originRequest->request_name = $name;
        $originRequest->retry_of_request_id = $retryOfRequestId;

        $originResult = new ProvisioningResult();
        $originResult->status = $status;
        $originRequest->setRelation('result', $originResult);

        return $originRequest;
    }
}
