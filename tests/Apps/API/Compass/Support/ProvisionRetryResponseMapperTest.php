<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Support;

use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Serializer;
use Tests\TestCase;
use Waterfront\Apps\API\Compass\Support\ProvisionRetryResponseMapper;
use Waterfront\Domain\Provision\Backup\Requests\GetBackupUsageRequest;
use Waterfront\Domain\Provision\Enums\ProvisionErrorMessage;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Exceptions\RetryOriginNotFoundException;
use Waterfront\Domain\Provision\Factories\ProvisionSerializeFactory;
use Waterfront\Domain\Provision\Results\ProvisionResult;
use Waterfront\Domain\Provision\Validation\ValidationResult;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(ProvisionRetryResponseMapper::class)]
class ProvisionRetryResponseMapperTest extends TestCase
{
    private ProvisionRetryResponseMapper $responseMapper;

    private ProvisionSerializeFactory&Stub $serializerFactory;

    private TranslatorInterface&MockObject $translatorMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializerFactory = self::createStub(ProvisionSerializeFactory::class);
        $this->translatorMock = self::createMock(TranslatorInterface::class);
        $this->responseMapper = new ProvisionRetryResponseMapper(
            serializerFactory: $this->serializerFactory,
            translator: $this->translatorMock,
        );
    }

    #[Test]
    public function fromResultReturnsNormalizedResult(): void
    {
        $result = new ProvisionResult(
            provisionData: new GetBackupUsageRequest(Uuid::uuid4()),
            provisionStatus: ProvisionStatus::SUCCESS,
        );
        $normalizedResult = [
            'provisionStatus' => ProvisionStatus::SUCCESS->value,
            'typeSpecificValue' => 'result',
        ];

        $serializerMock = self::createMock(Serializer::class);
        $serializerMock->expects(self::once())
            ->method('normalize')
            ->with($result)
            ->willReturn($normalizedResult);
        $this->serializerFactory->method('get')->willReturn($serializerMock);
        $this->translatorMock->expects(self::never())->method('translate');

        $response = $this->responseMapper->fromResult($result);

        self::assertSame(200, $response->status());
        self::assertSame($normalizedResult, $response->getData(true));
    }

    #[Test]
    public function fromResultReturnsTranslatedValidationErrors(): void
    {
        $result = new ProvisionResult(
            provisionData: new GetBackupUsageRequest(Uuid::uuid4()),
            provisionStatus: ProvisionStatus::VALIDATION_ERROR,
            validationResult: new ValidationResult(false, [
                'retryOf' => [ProvisionErrorMessage::RETRY_ORIGIN_NOT_FAILED->value],
                'items.0.email' => ['Invalid email.'],
            ]),
        );

        $this->translatorMock->expects(self::once())
            ->method('translate')
            ->with('provision.errors.retry_origin_not_failed')
            ->willReturn('Translated retry error.');

        $response = $this->responseMapper->fromResult($result);

        self::assertSame(422, $response->status());
        self::assertSame([
            'message' => '',
            'errors' => [
                'retryOf' => ['Translated retry error.'],
                'retryData.items.0.email' => ['Invalid email.'],
            ],
        ], $response->getData(true));
    }

    #[Test]
    public function fromResultReturnsInternalServerErrorWhenNormalizationFails(): void
    {
        $result = new ProvisionResult(
            provisionData: new GetBackupUsageRequest(Uuid::uuid4()),
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $serializerMock = self::createMock(Serializer::class);
        $serializerMock->expects(self::once())
            ->method('normalize')
            ->with($result)
            ->willThrowException(new NotNormalizableValueException('Technical serializer message'));
        $this->serializerFactory->method('get')->willReturn($serializerMock);
        $this->translatorMock->expects(self::once())
            ->method('translate')
            ->with('provision.retry.execution_failed')
            ->willReturn('Execution failed.');

        $response = $this->responseMapper->fromResult($result);

        self::assertSame(500, $response->status());
        self::assertSame([
            'message' => 'Execution failed.',
            'errors' => [],
        ], $response->getData(true));
    }

    #[Test]
    public function fromResultReturnsInternalServerErrorForFailedResult(): void
    {
        $result = new ProvisionResult(
            provisionData: new GetBackupUsageRequest(Uuid::uuid4()),
            provisionStatus: ProvisionStatus::FAILED,
            exception: new Exception('Technical details'),
        );

        $this->translatorMock->expects(self::once())
            ->method('translate')
            ->with('provision.retry.execution_failed')
            ->willReturn('Execution failed.');

        $response = $this->responseMapper->fromResult($result);

        self::assertSame(500, $response->status());
        self::assertSame([
            'message' => 'Execution failed.',
            'errors' => [],
        ], $response->getData(true));
    }

    #[Test]
    public function fromExceptionReturnsValidationErrorWhenRetryOriginIsNotFound(): void
    {
        $exception = new RetryOriginNotFoundException(Uuid::uuid4());

        $this->translatorMock->expects(self::once())
            ->method('translate')
            ->with('provision.errors.retry_origin_not_found')
            ->willReturn('Retry origin not found.');

        $response = $this->responseMapper->fromException($exception);

        self::assertSame(422, $response->status());
        self::assertSame([
            'message' => '',
            'errors' => [
                'retryOf' => ['Retry origin not found.'],
            ],
        ], $response->getData(true));
    }

    #[Test]
    public function fromExceptionReturnsInvalidRequestForSerializerException(): void
    {
        $exception = new NotNormalizableValueException('Technical serializer message');

        $this->translatorMock->expects(self::once())
            ->method('translate')
            ->with('provision.retry.invalid_request')
            ->willReturn('Invalid request.');

        $response = $this->responseMapper->fromException($exception);

        self::assertSame(422, $response->status());
        self::assertSame([
            'message' => 'Invalid request.',
            'errors' => [],
        ], $response->getData(true));
    }
}
