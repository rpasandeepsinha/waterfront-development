<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Hosting\Validation;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Str;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Hosting\Requests\HostingCreateRequest;
use Waterfront\Domain\Provision\Hosting\Validators\DirectAdminValidator;

#[CoversClass(DirectAdminValidator::class)]
class DirectAdminValidatorTest extends TestCase
{
    private DirectAdminValidator $directAdminValidator;

    private UuidInterface $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = Str::uuid();
        $translator = $this->createStub(Translator::class);
        $translator->method('get')
            ->willReturnCallback(fn (string $message): mixed => $message);
        $this->directAdminValidator = new DirectAdminValidator(new Factory($translator));
    }

    #[Test]
    public function createRequestValidationSuccess(): void
    {
        $createRequest = new HostingCreateRequest(
            servicePlan: 'package-name',
            email: 'valid@email.nl',
            context: $this->context,
        );

        $validator = $this->directAdminValidator->getCreateRequestValidator($createRequest);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function createRequestValidationFails(): void
    {
        $createRequest = new HostingCreateRequest(
            servicePlan: 'package-name',
            email: 'invalid-email',
            context: $this->context,
        );

        $validator = $this->directAdminValidator->getCreateRequestValidator($createRequest);

        self::assertTrue($validator->fails());
        self::assertArrayHasKey('email', $validator->messages()->toArray());
    }
}
