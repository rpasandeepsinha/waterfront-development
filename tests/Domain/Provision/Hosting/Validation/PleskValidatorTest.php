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
use Waterfront\Domain\Provision\Hosting\Validators\PleskValidator;

#[CoversClass(PleskValidator::class)]
class PleskValidatorTest extends TestCase
{
    private PleskValidator $pleskValidator;

    private UuidInterface $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = Str::uuid();
        $translator = $this->createStub(Translator::class);
        $translator->method('get')->willReturnCallback(fn (string $message): mixed => $message);
        $this->pleskValidator = new PleskValidator(new Factory($translator));
    }

    #[Test]
    public function createRequestValidationSuccess(): void
    {
        $createRequest = new HostingCreateRequest(
            servicePlan: 'servicePlan',
            email: 'valid@email.nl',
            contactName: 'contactName',
            ipv4: '1.1.1.1',
            context: $this->context,
        );

        $validator = $this->pleskValidator->getCreateRequestValidator($createRequest);

        self::assertTrue($validator->passes());
    }

    #[Test]
    public function createRequestValidationFails(): void
    {
        $createRequest = new HostingCreateRequest(
            servicePlan: 'servicePlan',
            email: 'invalid-email',
            ipv4: 'no-ip',
            context: $this->context,
        );

        $validator = $this->pleskValidator->getCreateRequestValidator($createRequest);

        self::assertTrue($validator->fails());
        self::assertArrayHasKey('contactName', $validator->messages()->toArray());
        self::assertArrayHasKey('ipv4', $validator->messages()->toArray());
        self::assertArrayHasKey('email', $validator->messages()->toArray());
    }
}
