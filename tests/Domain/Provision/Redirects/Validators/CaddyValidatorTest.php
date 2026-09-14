<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Redirects\Validators;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Validation\DatabasePresenceVerifierInterface;
use Illuminate\Validation\Factory;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Tests\TestCase;
use Waterfront\Domain\Domains\Rules\RedirectDestinationUrlRule;
use Waterfront\Domain\Domains\Rules\RedirectFromUrlRule;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Domain\Provision\Redirects\Exceptions\UnknownRedirectRequestException;
use Waterfront\Domain\Provision\Redirects\Requests\CreateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\DeleteRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\GetRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\ListRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Requests\SuspendRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\TerminateRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Requests\UnsuspendRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\UpdateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Validators\CaddyValidator;

#[CoversClass(CaddyValidator::class)]
class CaddyValidatorTest extends TestCase
{
    private CaddyValidator $validator;

    private DatabasePresenceVerifierInterface&MockInterface $databaseExistsMock;

    private UuidInterface $context;

    protected function setUp(): void
    {
        parent::setUp();

        $databaseExistsMock = self::mock(DatabasePresenceVerifierInterface::class);
        $this->databaseExistsMock = $databaseExistsMock;
        $this->databaseExistsMock->expects('setConnection')->zeroOrMoreTimes();

        $translator = $this->createStub(Translator::class);
        $translator->method('get')->willReturnCallback(fn (string $message): mixed => $message);
        $this->app->bind(Translator::class, fn (): Translator => $translator);
        $validatorFactory = new Factory($translator);
        $validatorFactory->setPresenceVerifier($databaseExistsMock);

        $this->validator = new CaddyValidator(
            validatorFactory: $validatorFactory,
            redirectFromUrlRule: $this->app->make(RedirectFromUrlRule::class),
            redirectDestinationUrlRule: $this->app->make(RedirectDestinationUrlRule::class),
        );

        $this->context = Uuid::uuid4();
    }

    #[Test]
    public function getValidatorByRequestWillThrowUnknownRedirectRequestException(): void
    {
        self::expectException(UnknownRedirectRequestException::class);
        $this->validator->getValidatorByRequest(self::createStub(ProvisionRequestInterface::class));
    }

    #[Test]
    public function createCaddyValidationSuccess(): void
    {
        $this->databaseExistsMock->shouldReceive('getCount')->once()->andReturn(0);

        $request = new CreateRedirectRequest(
            domain: 'yourhosting.nl',
            destinationUrl: 'versio.nl',
            redirectType: RedirectType::PERMANENT,
            context: $this->context,
        );

        $validator = $this->validator->getCreateRedirectValidator($request);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function createCaddyValidationDomainAlreadyExists(): void
    {
        $this->databaseExistsMock->shouldReceive('getCount')->once()->andReturn(1);

        $request = new CreateRedirectRequest(
            domain: 'yourhosting.nl',
            destinationUrl: 'versio.nl',
            redirectType: RedirectType::PERMANENT,
            context: $this->context,
        );

        $validator = $this->validator->getCreateRedirectValidator($request);

        self::assertTrue($validator->fails());
        self::assertSame(['validation.unique'], $validator->messages()->get('domain'));
    }

    #[Test]
    public function createCaddyValidationInvalidDomain(): void
    {
        $this->databaseExistsMock->shouldReceive('getCount')->never();

        $request = new CreateRedirectRequest(
            domain: 'invalid',
            destinationUrl: 'versio.nl',
            redirectType: RedirectType::PERMANENT,
            context: $this->context,
        );

        $validator = $this->validator->getCreateRedirectValidator($request);

        self::assertTrue($validator->fails());
        self::assertSame(['validation.domain_name'], $validator->messages()->get('domain'));
    }

    #[Test]
    public function getRedirectValidatorSuccess(): void
    {
        $this->databaseExistsMock->shouldReceive('getCount')->twice()->andReturn(1, 1);

        $request = new GetRedirectRequest(
            domainName: 'yourhosting.nl',
            context: $this->context,
        );

        $validator = $this->validator->getRedirectValidator($request);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function getRedirectValidatorInvalidDomain(): void
    {
        $this->databaseExistsMock->shouldReceive('getCount')->once()->andReturn(1);

        $request = new GetRedirectRequest(
            domainName: 'invalid',
            context: $this->context,
        );

        $validator = $this->validator->getRedirectValidator($request);

        self::assertTrue($validator->fails());
        self::assertSame(['validation.domain_name'], $validator->messages()->get('domainName'));
    }

    #[Test]
    public function listRedirectsValidatorSuccess(): void
    {
        $request = new ListRedirectsRequest(
            context: $this->context,
        );

        $validator = $this->validator->getListRedirectsValidator($request);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function listRedirectsValidatorAcceptsNonVersionFourContext(): void
    {
        $request = new ListRedirectsRequest(
            context: Uuid::uuid1(),
        );

        $validator = $this->validator->getListRedirectsValidator($request);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function updateRedirectValidatorSuccess(): void
    {
        $this->databaseExistsMock->shouldReceive('getCount')->twice()->andReturn(1, 1);

        $request = new UpdateRedirectRequest(
            oldSource: 'yourhosting.nl',
            newSource: 'yourhosting.nl',
            destinationUrl: 'versio.nl',
            redirectType: RedirectType::PERMANENT,
            context: $this->context,
        );

        $validator = $this->validator->getUpdateRedirectValidator($request);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function updateRedirectValidatorInvalidOldSource(): void
    {
        $this->databaseExistsMock->shouldReceive('getCount')->once()->andReturn(1);

        $request = new UpdateRedirectRequest(
            oldSource: 'invalid',
            newSource: 'yourhosting.nl',
            destinationUrl: 'versio.nl',
            redirectType: RedirectType::PERMANENT,
            context: $this->context,
        );

        $validator = $this->validator->getUpdateRedirectValidator($request);

        self::assertTrue($validator->fails());
        self::assertSame(['validation.domain_name'], $validator->messages()->get('oldSource'));
    }

    #[Test]
    public function updateRedirectValidatorInvalidNewSource(): void
    {
        $this->databaseExistsMock->shouldReceive('getCount')->twice()->andReturn([0, 1]);

        $request = new UpdateRedirectRequest(
            oldSource: 'yourhosting.nl',
            newSource: 'invalid',
            destinationUrl: 'versio.nl',
            redirectType: RedirectType::PERMANENT,
            context: $this->context,
        );

        $validator = $this->validator->getUpdateRedirectValidator($request);

        self::assertTrue($validator->fails());
        self::assertSame(['validation.domain_name'], $validator->messages()->get('newSource'));
    }

    #[Test]
    public function updateRedirectValidatorInvalidDestinationUrl(): void
    {
        $this->databaseExistsMock->shouldReceive('getCount')->twice()->andReturn(1, 1);

        $request = new UpdateRedirectRequest(
            oldSource: 'yourhosting.nl',
            newSource: 'yourhosting.nl',
            destinationUrl: 'invalid',
            redirectType: RedirectType::PERMANENT,
            context: $this->context,
        );

        $validator = $this->validator->getUpdateRedirectValidator($request);

        self::assertTrue($validator->fails());
        self::assertSame(['validation.domain_name'], $validator->messages()->get('destinationUrl'));
    }

    #[Test]
    public function updateRedirectValidatorContextDoesNotExist(): void
    {
        $this->databaseExistsMock->shouldReceive('getCount')->twice()->andReturn(1, 0);

        $request = new UpdateRedirectRequest(
            oldSource: 'yourhosting.nl',
            newSource: 'yourhosting.nl',
            destinationUrl: 'versio.nl',
            redirectType: RedirectType::PERMANENT,
            context: $this->context,
        );

        $validator = $this->validator->getUpdateRedirectValidator($request);

        self::assertTrue($validator->fails());
        self::assertSame(['validation.exists'], $validator->messages()->get('context'));
    }

    #[Test]
    public function deleteRedirectValidatorSuccess(): void
    {
        $this->databaseExistsMock->shouldReceive('getCount')->twice()->andReturn(1, 1);

        $request = new DeleteRedirectRequest(
            domainName: 'yourhosting.nl',
            context: $this->context,
        );

        $validator = $this->validator->getDeleteRedirectValidator($request);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function deleteRedirectValidatorInvalidDomain(): void
    {
        $this->databaseExistsMock->shouldReceive('getCount')->once()->andReturn(1);

        $request = new DeleteRedirectRequest(
            domainName: 'invalid',
            context: $this->context,
        );

        $validator = $this->validator->getDeleteRedirectValidator($request);

        self::assertTrue($validator->fails());
        self::assertSame(['validation.domain_name'], $validator->messages()->get('domainName'));
    }

    #[Test]
    public function deleteRedirectValidatorContextDoesNotExist(): void
    {
        $this->databaseExistsMock->shouldReceive('getCount')->twice()->andReturn(0, 0);

        $request = new DeleteRedirectRequest(
            domainName: 'yourhosting.nl',
            context: Uuid::uuid4(),
        );

        $validator = $this->validator->getDeleteRedirectValidator($request);

        self::assertTrue($validator->fails());
        self::assertSame(['validation.exists'], $validator->messages()->get('domainName'));
        self::assertSame(['validation.exists'], $validator->messages()->get('context'));
    }

    #[Test]
    public function terminateRedirectsValidatorSuccess(): void
    {
        $this->databaseExistsMock->shouldReceive('getCount')->once()->andReturn(1);

        $request = new TerminateRedirectsRequest(
            context: $this->context,
        );

        $validator = $this->validator->getTerminateRedirectsValidator($request);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function terminateRedirectsValidatorContextDoesNotExist(): void
    {
        $this->databaseExistsMock->shouldReceive('getCount')->once()->andReturn(0);

        $request = new TerminateRedirectsRequest(
            context: Uuid::uuid4(),
        );

        $validator = $this->validator->getTerminateRedirectsValidator($request);

        self::assertTrue($validator->fails());
        self::assertSame(['validation.exists'], $validator->messages()->get('context'));
    }

    #[Test]
    public function suspendRedirectValidatorSuccess(): void
    {
        $this->databaseExistsMock->shouldReceive('getCount')->once()->andReturn(1);

        $request = new SuspendRedirectRequest(
            context: $this->context,
        );

        $validator = $this->validator->getContextOnlyValidator($request);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function suspendRedirectValidatorContextDoesNotExist(): void
    {
        $this->databaseExistsMock->shouldReceive('getCount')->once()->andReturn(0);

        $request = new SuspendRedirectRequest(
            context: $this->context,
        );

        $validator = $this->validator->getContextOnlyValidator($request);

        self::assertTrue($validator->fails());
        self::assertSame(['validation.exists'], $validator->messages()->get('context'));
    }

    #[Test]
    public function unsuspendRedirectValidatorSuccess(): void
    {
        $this->databaseExistsMock->shouldReceive('getCount')->once()->andReturn(1);

        $request = new UnsuspendRedirectRequest(
            context: $this->context,
        );

        $validator = $this->validator->getContextOnlyValidator($request);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function unsuspendRedirectValidatorContextDoesNotExist(): void
    {
        $this->databaseExistsMock->shouldReceive('getCount')->once()->andReturn(0);

        $request = new UnsuspendRedirectRequest(
            context: $this->context,
        );

        $validator = $this->validator->getContextOnlyValidator($request);

        self::assertTrue($validator->fails());
        self::assertSame(['validation.exists'], $validator->messages()->get('context'));
    }
}
