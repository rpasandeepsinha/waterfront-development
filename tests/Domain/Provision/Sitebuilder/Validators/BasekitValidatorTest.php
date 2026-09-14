<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Sitebuilder\Validators;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Validation\DatabasePresenceVerifierInterface;
use Illuminate\Validation\Factory;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Tests\TestCase;
use Waterfront\Domain\Domains\Rules\DomainNameRule;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\UnknownSitebuilderRequestException;
use Waterfront\Domain\Provision\Sitebuilder\Requests\AddSslSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\CreateBasekitDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\CreateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetBasekitSiteByRefRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetBasekitUserByRefRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetSitebuilderSsoRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\RollbackBasekitDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\TerminateSitebuilderContextRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\TerminateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\UpdateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Validators\BasekitValidator;

#[CoversClass(BasekitValidator::class)]
class BasekitValidatorTest extends TestCase
{
    private BasekitValidator $validator;

    private DatabasePresenceVerifierInterface&MockInterface $databaseExistsMock;

    private ProvisioningRequestRepository&MockInterface $mockRequestRepository;

    private UuidInterface $context;

    protected function setUp(): void
    {
        parent::setUp();

        $databaseExistsMock = self::mock(DatabasePresenceVerifierInterface::class);
        $this->databaseExistsMock = $databaseExistsMock;
        $this->databaseExistsMock->expects('setConnection')->zeroOrMoreTimes();
        $this->mockRequestRepository = self::mock(ProvisioningRequestRepository::class);

        $this->app->bind(ProvisioningRequestRepository::class, fn () => $this->mockRequestRepository);

        $translator = self::createStub(Translator::class);
        $translator->method('get')->willReturnCallback(fn (string $message): mixed => $message);

        $validatorFactory = new Factory($translator);
        $validatorFactory->setPresenceVerifier($databaseExistsMock);

        $this->validator = new BasekitValidator(
            validatorFactory: $validatorFactory,
            domainNameRule: self::createStub(DomainNameRule::class),
        );

        $this->context = Uuid::uuid4();
    }

    #[Test]
    public function getValidatorByRequestWillThrowNoImplementedSitebuilderValidatorException(): void
    {
        self::expectException(UnknownSitebuilderRequestException::class);
        $this->validator->getValidatorByRequest(self::createStub(ProvisionRequestInterface::class));
    }

    #[Test]
    public function createSitebuilderValidationSuccess(): void
    {
        $createSitebuilderRequest = new CreateSitebuilderRequest(
            domain: 'yourhosting.nl',
            packages: [1337],
            firstname: 'John',
            lastname: 'Doe',
            email: 'noreply@yourhosting.nl',
            contractPeriod: 12,
            context: Uuid::uuid4(),
        );

        $validator = $this->validator->getCreateSitebuilderValidator($createSitebuilderRequest);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function createSitebuilderValidationAcceptsNonVersionFourContext(): void
    {
        $createSitebuilderRequest = new CreateSitebuilderRequest(
            domain: 'yourhosting.nl',
            packages: [1337],
            firstname: 'John',
            lastname: 'Doe',
            email: 'noreply@yourhosting.nl',
            contractPeriod: 12,
            context: Uuid::uuid1(),
        );

        $validator = $this->validator->getCreateSitebuilderValidator($createSitebuilderRequest);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function createSitebuilderValidationFails(): void
    {
        $createSitebuilderRequest = new CreateSitebuilderRequest(
            domain: str_repeat('.', 256),
            packages: [],
            firstname: '',
            lastname: '',
            email: 'not-an-email-address',
            contractPeriod: 0,
            context: Uuid::uuid1(),
        );

        $validator = $this->validator->getCreateSitebuilderValidator($createSitebuilderRequest);

        self::assertTrue($validator->fails());
        self::assertSame(['validation.max.string'], $validator->messages()->get('domain'));
        self::assertSame(['validation.required'], $validator->messages()->get('packages'));
        self::assertSame(['validation.required'], $validator->messages()->get('firstname'));
        self::assertSame(['validation.required'], $validator->messages()->get('lastname'));
        self::assertSame(['validation.email'], $validator->messages()->get('email'));
        self::assertSame(['validation.min.numeric'], $validator->messages()->get('contractPeriod'));
        self::assertCount(0, $validator->messages()->get('context'));

        $createSitebuilderRequest = new CreateSitebuilderRequest(
            domain: str_repeat('.', 256),
            packages: ['some random string'], // ignore on purpose, to test if validator fails when string is given @phpstan-ignore argument.type
            firstname: str_repeat('a', 151),
            lastname: str_repeat('b', 151),
            email: str_repeat('c', 240) . '@yourhosting.nl',
            contractPeriod: 1,
            context: Uuid::uuid4(),
        );
        $validator = $this->validator->getCreateSitebuilderValidator($createSitebuilderRequest);

        self::assertTrue($validator->fails());
        self::assertSame(['validation.integer'], $validator->messages()->get('packages.0'));
        self::assertSame(['validation.max.string'], $validator->messages()->get('domain'));
        self::assertSame(['validation.max.string'], $validator->messages()->get('firstname'));
        self::assertSame(['validation.max.string'], $validator->messages()->get('lastname'));
        self::assertSame(['validation.max.string'], $validator->messages()->get('email'));
        self::assertCount(0, $validator->messages()->get('contractPeriod'));
        self::assertCount(0, $validator->messages()->get('context'));
    }

    #[Test]
    public function createBasekitFromMigrationValidationSuccess(): void
    {
        $createBasekitDeploymentsFromMigrationRequest = new CreateBasekitDeploymentsFromMigrationRequest(
            domain: 'yourhosting.nl',
            userRef: 1111,
            siteRef: 2222,
            context: Uuid::uuid4(),
        );

        $createBasekitDeploymentsFromMigrationRequest->provider = ProvisionProvider::BASEKIT;

        $validator = $this->validator->getCreateBasekitFromMigrationValidator(
            $createBasekitDeploymentsFromMigrationRequest,
        );

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function createBasekitFromMigrationValidationFails(): void
    {
        $createBasekitDeploymentsFromMigrationRequest = new CreateBasekitDeploymentsFromMigrationRequest(
            domain: str_repeat('.', 256),
            userRef: 1111,
            siteRef: 2222,
            context: Uuid::uuid1(),
        );

        $createBasekitDeploymentsFromMigrationRequest->provider = ProvisionProvider::MICROSOFT_ONLINE;

        $validator = $this->validator->getCreateBasekitFromMigrationValidator(
            $createBasekitDeploymentsFromMigrationRequest,
        );

        self::assertTrue($validator->fails());
        self::assertSame(['The selected provider is invalid.'], $validator->messages()->get('provider'));
        self::assertSame(['validation.max.string'], $validator->messages()->get('domain'));
        self::assertCount(0, $validator->messages()->get('context'));
    }

    #[Test]
    public function rollbackBasekitFromMigrationValidationSuccess(): void
    {
        $expectedTag = Uuid::uuid4();

        $this->databaseExistsMock
            ->shouldReceive('getCount')
            ->once()
            ->with('sitebuilder_context_basekit', 'context_uuid', $this->context->toString(), null, null, [])
            ->andReturn(1);

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($expectedTag->toString(), ProvisionType::SITEBUILDER)
            ->andReturn(1);

        $rollbackBasekitDeploymentsRequest = new RollbackBasekitDeploymentsFromMigrationRequest(
            context: $this->context,
            tagUuid: $expectedTag,
        );

        $validator = $this->validator->getRollbackBasekitFromMigrationValidator($rollbackBasekitDeploymentsRequest);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function rollbackBasekitFromMigrationWithMissingContextAndTagValidationError(): void
    {
        $tagUuid = Uuid::uuid4();

        $this->databaseExistsMock->shouldReceive('getCount')->once()->andReturn(0);

        $rollbackBasekitDeploymentsRequest = new RollbackBasekitDeploymentsFromMigrationRequest(
            context: Uuid::uuid4(),
            tagUuid: $tagUuid,
        );

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($tagUuid->toString(), ProvisionType::SITEBUILDER)
            ->andReturn(0);

        $validator = $this->validator->getRollbackBasekitFromMigrationValidator($rollbackBasekitDeploymentsRequest);

        self::assertTrue($validator->fails());
        self::assertSame(['validation.exists'], $validator->messages()->get('context'));
        self::assertSame(
            ['No create request with this tag in the [sitebuilder] type.'],
            $validator->messages()->get('tag'),
        );
    }

    #[Test]
    public function getSsoRequestValidationSuccess(): void
    {
        $expectedTag = Uuid::uuid4();

        $this->databaseExistsMock
            ->shouldReceive('getCount')
            ->once()
            ->with('sitebuilder_context_basekit', 'context_uuid', $this->context->toString(), null, null, [])
            ->andReturn(1);

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($expectedTag->toString(), ProvisionType::SITEBUILDER)
            ->andReturn(1);

        $getSsoRequest = new GetSitebuilderSsoRequest(
            context: $this->context,
            tagUuid: $expectedTag,
        );

        $validator = $this->validator->getSsoRequestValidator($getSsoRequest);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function getSsoWithMissingContextAndTagValidationError(): void
    {
        $tagUuid = Uuid::uuid4();

        $this->databaseExistsMock->shouldReceive('getCount')->once()->andReturn(0);

        $getSsoRequest = new GetSitebuilderSsoRequest(
            context: Uuid::uuid4(),
            tagUuid: $tagUuid,
        );

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($tagUuid->toString(), ProvisionType::SITEBUILDER)
            ->andReturn(0);

        $validator = $this->validator->getSsoRequestValidator($getSsoRequest);

        self::assertTrue($validator->fails());
        self::assertSame(['validation.exists'], $validator->messages()->get('context'));
        self::assertSame(
            ['No create request with this tag in the [sitebuilder] type.'],
            $validator->messages()->get('tag'),
        );
    }

    #[Test]
    public function terminateRequestValidationSuccess(): void
    {
        $expectedRequestUuid = Uuid::uuid4();

        $this->databaseExistsMock
            ->shouldReceive('getCount')
            ->once()
            ->with('sitebuilder_context_basekit', 'context_uuid', $this->context->toString(), null, null, [])
            ->andReturn(1);

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($expectedRequestUuid->toString(), ProvisionType::SITEBUILDER)
            ->andReturn(1);

        $terminateRequest = new TerminateSitebuilderRequest(
            context: $this->context,
            tagUuid: $expectedRequestUuid,
        );

        $validator = $this->validator->getTerminateSitebuilderSiteRequestValidator($terminateRequest);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function terminateRequestValidationFailsIfNotExists(): void
    {
        $this->databaseExistsMock->shouldReceive('getCount')->once()->andReturn(0);

        $tagUuid = Uuid::uuid4();

        $terminateRequest = new TerminateSitebuilderRequest(
            context: Uuid::uuid4(),
            tagUuid: $tagUuid,
        );

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($tagUuid->toString(), ProvisionType::SITEBUILDER)
            ->andReturn(0);

        $validator = $this->validator->getTerminateSitebuilderSiteRequestValidator($terminateRequest);

        self::assertTrue($validator->fails());
        self::assertSame(['validation.exists'], $validator->messages()->get('context'));
        self::assertSame(
            ['No create request with this tag in the [sitebuilder] type.'],
            $validator->messages()->get('tag'),
        );
    }

    #[Test]
    public function getValidatorByRequestDispatchesToTerminateValidator(): void
    {
        $expectedTag = Uuid::uuid4();

        $this->databaseExistsMock
            ->shouldReceive('getCount')
            ->once()
            ->with('sitebuilder_context_basekit', 'context_uuid', $this->context->toString(), null, null, [])
            ->andReturn(1);

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($expectedTag->toString(), ProvisionType::SITEBUILDER)
            ->andReturn(1);

        $terminateRequest = new TerminateSitebuilderRequest(
            context: $this->context,
            tagUuid: $expectedTag,
        );

        $validator = $this->validator->getValidatorByRequest($terminateRequest);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function addSslValidationSuccess(): void
    {
        $expectedTag = Uuid::uuid4();

        $this->databaseExistsMock
            ->shouldReceive('getCount')
            ->once()
            ->with('sitebuilder_context_basekit', 'context_uuid', $this->context->toString(), null, null, [])
            ->andReturn(1);

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($expectedTag->toString(), ProvisionType::SITEBUILDER)
            ->andReturn(1);

        $addSslRequest = new AddSslSitebuilderRequest(
            tagUuid: $expectedTag,
            context: $this->context,
            privateKey: 'privateKey',
            mainCertificate: 'mainCertificate',
        );

        $validator = $this->validator->getAddSslSitebuilderValidator($addSslRequest);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function terminateContextRequestValidationFailsIfNotExists(): void
    {
        $this->databaseExistsMock->shouldReceive('getCount')->once()->andReturn(0);

        $terminateContextRequest = new TerminateSitebuilderContextRequest(
            context: Uuid::uuid4(),
        );

        $validator = $this->validator->getTerminateContextRequestValidator($terminateContextRequest);

        self::assertTrue($validator->fails());
        self::assertSame(['validation.exists'], $validator->messages()->get('context'));
    }

    #[Test]
    public function terminateContextRequestValidation(): void
    {
        $this->databaseExistsMock->shouldReceive('getCount')->once()->andReturn(1);

        $terminateContextRequest = new TerminateSitebuilderContextRequest(
            context: Uuid::uuid4(),
        );

        $validator = $this->validator->getTerminateContextRequestValidator($terminateContextRequest);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function updateSitebuilderValidationSuccess(): void
    {
        $tag = Uuid::uuid4();
        $context = Uuid::uuid4();

        $request = new UpdateSitebuilderRequest(
            tagUuid: $tag,
            context: $context,
            packages: [1337],
            contractPeriod: 12,
        );

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($tag->toString(), ProvisionType::SITEBUILDER)
            ->andReturn(1);

        $this->databaseExistsMock
            ->shouldReceive('getCount')
            ->once()
            ->with('sitebuilder_context_basekit', 'context_uuid', $context->toString(), null, null, [])
            ->andReturn(1);

        $validator = $this->validator->getUpdateRequestValidator($request);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function updateSitebuilderValidationFails(): void
    {
        $legacyTag = Uuid::uuid1();
        $legacyContext = Uuid::uuid1();

        $request = new UpdateSitebuilderRequest(
            tagUuid: $legacyTag,
            context: $legacyContext,
            packages: [],
            contractPeriod: 0,
        );

        $this->databaseExistsMock
            ->shouldReceive('getCount')
            ->once()
            ->with('sitebuilder_context_basekit', 'context_uuid', $legacyContext->toString(), null, null, [])
            ->andReturn(1);

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($legacyTag->toString(), ProvisionType::SITEBUILDER)
            ->andReturn(1);

        $validator = $this->validator->getUpdateRequestValidator($request);

        self::assertTrue($validator->fails());
        self::assertCount(0, $validator->messages()->get('tag'));
        self::assertCount(0, $validator->messages()->get('context'));
        self::assertSame(['validation.required'], $validator->messages()->get('packages'));
        self::assertSame(['validation.min.numeric'], $validator->messages()->get('contractPeriod'));

        $expectedTag = Uuid::uuid4();

        $request = new UpdateSitebuilderRequest(
            tagUuid: $expectedTag,
            context: $context = Uuid::uuid4(),
            packages: ['some random string'], // ignore on purpose, to test if validator fails when string is given @phpstan-ignore argument.type
            contractPeriod: 1,
        );

        $this->databaseExistsMock
            ->shouldReceive('getCount')
            ->once()
            ->with('sitebuilder_context_basekit', 'context_uuid', $context->toString(), null, null, [])
            ->andReturn(1);

        $this->mockRequestRepository
            ->expects('createRequestCount')
            ->once()
            ->with($expectedTag->toString(), ProvisionType::SITEBUILDER)
            ->andReturn(3);

        $validator = $this->validator->getUpdateRequestValidator($request);
        self::assertTrue($validator->fails());
        self::assertSame(['validation.integer'], $validator->messages()->get('packages.0'));
        self::assertSame(
            ['The tag has multiple create requests linked for [sitebuilder] type.'],
            $validator->messages()->get('tag'),
        );
    }

    #[Test]
    public function getBasekitSiteByRefValidationSuccess(): void
    {
        $getBasekitSiteByRefRequest = new GetBasekitSiteByRefRequest(
            context: $this->context,
            siteRef: 123,
        );

        $getBasekitSiteByRefRequest->provider = ProvisionProvider::BASEKIT;

        $validator = $this->validator->getGetBasekitSiteByRefRequestValidator($getBasekitSiteByRefRequest);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function getBasekitSiteByRefValidationFailsWithWrongProvider(): void
    {
        $getBasekitSiteByRefRequest = new GetBasekitSiteByRefRequest(
            context: $this->context,
            siteRef: 123,
        );

        $getBasekitSiteByRefRequest->provider = ProvisionProvider::RTR;

        $validator = $this->validator->getGetBasekitSiteByRefRequestValidator($getBasekitSiteByRefRequest);

        self::assertFalse($validator->passes());
    }

    #[Test]
    public function getBasekitUserByRefValidationSuccess(): void
    {
        $getBasekitUserByRefRequest = new GetBasekitUserByRefRequest(
            context: $this->context,
            userRef: 123,
        );

        $getBasekitUserByRefRequest->provider = ProvisionProvider::BASEKIT;

        $validator = $this->validator->getGetBasekitUserByRefRequestValidator($getBasekitUserByRefRequest);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function getBasekitUserByRefValidationFailsWithWrongProvider(): void
    {
        $getBasekitUserByRefRequest = new GetBasekitUserByRefRequest(
            context: $this->context,
            userRef: 123,
        );

        $getBasekitUserByRefRequest->provider = ProvisionProvider::RTR;

        $validator = $this->validator->getGetBasekitUserByRefRequestValidator($getBasekitUserByRefRequest);

        self::assertFalse($validator->passes());
    }
}
