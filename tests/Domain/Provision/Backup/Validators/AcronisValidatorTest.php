<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Backup\Validators;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Str;
use Illuminate\Validation\Factory;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;
use Waterfront\Domain\Provision\Backup\Acronis\Enums\Language;
use Waterfront\Domain\Provision\Backup\Exceptions\UnknownBackupRequestException;
use Waterfront\Domain\Provision\Backup\Requests\CreateBackupRequest;
use Waterfront\Domain\Provision\Backup\Requests\GetBackupSsoRequest;
use Waterfront\Domain\Provision\Backup\Requests\GetBackupUsageRequest;
use Waterfront\Domain\Provision\Backup\Requests\SetBackupSuspensionStateRequest;
use Waterfront\Domain\Provision\Backup\Requests\TerminateBackupRequest;
use Waterfront\Domain\Provision\Backup\Requests\UpdateBackupRequest;
use Waterfront\Domain\Provision\Backup\Validators\AcronisValidator;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;

#[CoversClass(AcronisValidator::class)]
class AcronisValidatorTest extends TestCase
{
    private AcronisValidator $validator;

    private ProvisioningRequestRepository&MockInterface $mockRequestRepository;

    private Factory $validationFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRequestRepository = self::mock(ProvisioningRequestRepository::class);
        $this->app->bind(ProvisioningRequestRepository::class, fn () => $this->mockRequestRepository);

        $translator = self::createStub(Translator::class);
        $translator
            ->method('get')
            ->willReturnCallback(fn (string $message): mixed => $message);

        $this->validationFactory = new Factory($translator);

        $this->validator = new AcronisValidator(
            validatorFactory: $this->validationFactory,
        );
    }

    #[Test]
    public function getValidatorByRequestWillThrowNoImplementedBackupValidatorException(): void
    {
        self::expectException(UnknownBackupRequestException::class);
        $this->validator->getValidatorByRequest(self::createStub(ProvisionRequestInterface::class));
    }

    #[Test]
    public function getBackupSsoRequestValidationSuccess(): void
    {
        $expectedTag = Uuid::uuid4();

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($expectedTag->toString(), ProvisionType::BACKUP)
            ->andReturn(1);

        $getBackupSsoRequest = new GetBackupSsoRequest(
            tagUuid: $expectedTag,
        );

        $validator = $this->validator->getBackupSsoRequestValidator($getBackupSsoRequest);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function getBackupSsoRequestValidationAcceptsNonVersionFourTag(): void
    {
        $expectedTag = Uuid::uuid1();

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($expectedTag->toString(), ProvisionType::BACKUP)
            ->andReturn(1);

        $getBackupSsoRequest = new GetBackupSsoRequest(
            tagUuid: $expectedTag,
        );

        $validator = $this->validator->getBackupSsoRequestValidator($getBackupSsoRequest);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function createBackupValidationSuccess(): void
    {
        $tag = Uuid::uuid4();

        $this->mockRequestRepository
            ->expects('createRequestExists')
            ->once()
            ->with($tag->toString(), ProvisionType::BACKUP)
            ->andReturn(false);

        $createBackupRequest = new CreateBackupRequest(
            tagUuid: $tag,
            email: 'test@testkees.nl',
            firstname: 'test',
            lastname: 'kees',
            cloudStorageInGb: 10,
            localStorageInGb: 5,
            language: Language::CHINESE
        );

        $validator = $this->validator->getCreateBackupValidator($createBackupRequest);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function getBackupSsoRequestWithMissingTagValidationError(): void
    {
        $tagUuid = Uuid::uuid4();

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($tagUuid->toString(), ProvisionType::BACKUP)
            ->andReturn(0);

        $getBackupSsoRequest = new GetBackupSsoRequest(
            tagUuid: $tagUuid,
        );

        $validator = $this->validator->getBackupSsoRequestValidator($getBackupSsoRequest);

        self::assertTrue($validator->fails());
        self::assertSame(['No create request with this tag in the [backup] type.'], $validator->messages()->get('tag'));
    }

    #[Test]
    public function getBackupSsoRequestWithMultipleTagsValidationError(): void
    {
        $tagUuid = Uuid::uuid4();

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($tagUuid->toString(), ProvisionType::BACKUP)
            ->andReturn(2);

        $getBackupSsoRequest = new GetBackupSsoRequest(
            tagUuid: $tagUuid,
        );

        $validator = $this->validator->getBackupSsoRequestValidator($getBackupSsoRequest);

        self::assertTrue($validator->fails());
        self::assertSame(['The tag has multiple create requests linked for [backup] type.'], $validator->messages()->get('tag'));
    }

    #[Test]
    public function getValidatorByRequestWillThrowUnknownBackupRequestException(): void
    {
        self::expectException(UnknownBackupRequestException::class);
        $this->validator->getValidatorByRequest(self::createStub(ProvisionRequestInterface::class));
    }

    #[Test]
    public function terminateBackupRequestValidationSuccess(): void
    {
        $expectedRequestUuid = Uuid::uuid4();

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($expectedRequestUuid->toString(), ProvisionType::BACKUP)
            ->andReturn(1);

        $terminateBackupRequest = new TerminateBackupRequest(
            tagUuid: $expectedRequestUuid,
        );

        $validator = $this->validator->getTerminateBackupRequestValidator($terminateBackupRequest);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function terminateRequestValidationFailsIfNotExists(): void
    {
        $tagUuid = Uuid::uuid4();

        $terminateRequest = new TerminateBackupRequest(
            tagUuid: $tagUuid,
        );

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($tagUuid->toString(), ProvisionType::BACKUP)
            ->andReturn(0);

        $validator = $this->validator->getTerminateBackupRequestValidator($terminateRequest);

        self::assertTrue($validator->fails());
        self::assertSame(['No create request with this tag in the [backup] type.'], $validator->messages()->get('tag'));
    }

    #[Test]
    public function terminateRequestValidationFailsWhenMultipleTagsFound(): void
    {
        $tagUuid = Uuid::uuid4();

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($tagUuid->toString(), ProvisionType::BACKUP)
            ->andReturn(2);

        $terminateRequest = new TerminateBackupRequest(
            tagUuid: $tagUuid,
        );

        $validator = $this->validator->getTerminateBackupRequestValidator($terminateRequest);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['The tag has multiple create requests linked for [backup] type.'],
            $validator->messages()->get('tag')
        );
    }

    #[Test]
    public function getValidatorByRequestDispatchesToTerminateValidator(): void
    {
        $expectedTag = Uuid::uuid4();

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($expectedTag->toString(), ProvisionType::BACKUP)
            ->andReturn(1);

        $terminateRequest = new TerminateBackupRequest(
            tagUuid: $expectedTag,
        );

        $validator = $this->validator->getValidatorByRequest($terminateRequest);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function createBackupValidationFails(): void
    {
        $tag = Uuid::uuid4();

        $this->mockRequestRepository
            ->expects('createRequestExists')
            ->once()
            ->with($tag->toString(), ProvisionType::BACKUP)
            ->andReturn(true);

        // For some reason the Password rule uses the Validator facade internally.
        $this->app->bind('validator', fn () => $this->validationFactory);

        $createBackupRequest = new CreateBackupRequest(
            tagUuid: $tag,
            email: 'test@testkees.nl',
            firstname: Str::repeat('a', 256),
            lastname: Str::repeat('b', 256),
            cloudStorageInGb: 10,
            localStorageInGb: 10,
            username: Str::repeat('b', 256),
            password: 'weak',
        );

        $validator = $this->validator->getCreateBackupValidator($createBackupRequest);

        self::assertTrue($validator->fails());

        self::assertSame(['validation.max.string'], $validator->messages()->get('firstname'));
        self::assertSame(['validation.max.string'], $validator->messages()->get('lastname'));
        self::assertSame(['validation.max.string'], $validator->messages()->get('username'));
        self::assertSame(['A create request for tag already exists in the [backup] type.'], $validator->messages()->get('tagUuid'));
        self::assertSame(['validation.min.string', 'validation.password.mixed', 'validation.password.numbers'], $validator->messages()->get('password'));
    }

    #[Test]
    public function updateBackupRequestValidationSuccess(): void
    {
        $expectedTag = Uuid::uuid4();

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->with($expectedTag->toString(), ProvisionType::BACKUP)
            ->andReturn(1);

        $updateBackupRequest = new UpdateBackupRequest(
            tagUuid: $expectedTag,
            password: 'Pssword123!@#@#!!@#',
        );

        $validator = $this->validator->updateBackupValidator($updateBackupRequest);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function updateBackupRequestValidationOneResourceValueRequired(): void
    {
        $expectedTag = Uuid::uuid4();

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($expectedTag->toString(), ProvisionType::BACKUP)
            ->andReturn(1);

        $updateBackupRequest = new UpdateBackupRequest(
            tagUuid: $expectedTag,
        );

        $validator = $this->validator->updateBackupValidator($updateBackupRequest);

        self::assertFalse($validator->passes());
        self::assertSame(['At least one resource value must be provided.'], $validator->messages()->get('resources'));
    }

    #[Test]
    public function updateBackupRequestValidationFails(): void
    {
        $expectedTag = Uuid::uuid4();

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($expectedTag->toString(), ProvisionType::BACKUP)
            ->andReturn(1);

        // For some reason the Password rule uses the Validator facade internally.
        $this->app->bind('validator', fn () => $this->validationFactory);

        $updateBackupRequest = new UpdateBackupRequest(
            tagUuid: $expectedTag,
            password: 'password',
        );

        $validator = $this->validator->updateBackupValidator($updateBackupRequest);

        self::assertTrue($validator->fails());
        self::assertSame(['validation.min.string', 'validation.password.mixed', 'validation.password.numbers'], $validator->messages()->get('password'));
    }

    #[Test]
    public function setBackupSuspensionStateValidatorValidationSuccess(): void
    {
        $tagUuid = Uuid::uuid4();

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($tagUuid->toString(), ProvisionType::BACKUP)
            ->andReturn(1);

        $request = new SetBackupSuspensionStateRequest(
            tagUuid: $tagUuid,
            enable: true,
        );

        $validator = $this->validator->setBackupSuspensionStateValidator($request);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function setBackupSuspensionStateValidatorFailsIfNotExists(): void
    {
        $tagUuid = Uuid::uuid4();

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($tagUuid->toString(), ProvisionType::BACKUP)
            ->andReturn(0);

        $request = new SetBackupSuspensionStateRequest(
            tagUuid: $tagUuid,
            enable: false,
        );

        $validator = $this->validator->setBackupSuspensionStateValidator($request);

        self::assertTrue($validator->fails());
        self::assertSame(['No create request with this tag in the [backup] type.'], $validator->messages()->get('tag'));
    }

    #[Test]
    public function setBackupSuspensionStateValidatorFailsWhenMultipleTagsFound(): void
    {
        $tagUuid = Uuid::uuid4();

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($tagUuid->toString(), ProvisionType::BACKUP)
            ->andReturn(2);

        $request = new SetBackupSuspensionStateRequest(
            tagUuid: $tagUuid,
            enable: true,
        );

        $validator = $this->validator->setBackupSuspensionStateValidator($request);

        self::assertTrue($validator->fails());
        self::assertSame(['The tag has multiple create requests linked for [backup] type.'], $validator->messages()->get('tag'));
    }

    #[Test]
    public function getValidatorByRequestDispatchesToSetBackupSuspensionStateValidator(): void
    {
        $tagUuid = Uuid::uuid4();

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($tagUuid->toString(), ProvisionType::BACKUP)
            ->andReturn(1);

        $request = new SetBackupSuspensionStateRequest(
            tagUuid: $tagUuid,
            enable: true,
        );

        $validator = $this->validator->getValidatorByRequest($request);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function getBackupUsageRequestValidationSuccess(): void
    {
        $expectedTag = Uuid::uuid4();

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($expectedTag->toString(), ProvisionType::BACKUP)
            ->andReturn(1);

        $getBackupUsageRequest = new GetBackupUsageRequest(
            tagUuid: $expectedTag,
        );

        $validator = $this->validator->getBackupUsageRequestValidator($getBackupUsageRequest);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function getBackupUsageRequestWithMissingTagValidationError(): void
    {
        $tagUuid = Uuid::uuid4();

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($tagUuid->toString(), ProvisionType::BACKUP)
            ->andReturn(0);

        $getBackupUsageRequest = new GetBackupUsageRequest(
            tagUuid: $tagUuid,
        );

        $validator = $this->validator->getBackupUsageRequestValidator($getBackupUsageRequest);

        self::assertTrue($validator->fails());
        self::assertSame(['No create request with this tag in the [backup] type.'], $validator->messages()->get('tag'));
    }

    #[Test]
    public function getBackupUsageRequestWithMultipleTagsValidationError(): void
    {
        $tagUuid = Uuid::uuid4();

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($tagUuid->toString(), ProvisionType::BACKUP)
            ->andReturn(2);

        $getBackupUsageRequest = new GetBackupUsageRequest(
            tagUuid: $tagUuid,
        );

        $validator = $this->validator->getBackupUsageRequestValidator($getBackupUsageRequest);

        self::assertTrue($validator->fails());
        self::assertSame(['The tag has multiple create requests linked for [backup] type.'], $validator->messages()->get('tag'));
    }
}
