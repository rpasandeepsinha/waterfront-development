<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Microsoft365\Validation;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Str;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365TenantIdRequest;
use Waterfront\Domain\Provision\Microsoft365\Validators\MicrosoftOnlineValidator;

#[CoversClass(MicrosoftOnlineValidator::class)]
class MicrosoftOnlineValidatorTest extends TestCase
{
    private MicrosoftOnlineValidator $microsoftOnlineValidator;

    protected function setUp(): void
    {
        parent::setUp();
        $translator = $this->createStub(Translator::class);
        $translator->method('get')
            ->willReturnCallback(fn (string $message): mixed => $message);
        $this->microsoftOnlineValidator = new MicrosoftOnlineValidator(new Factory($translator));
    }

    #[Test]
    public function createRequestValidationSuccess(): void
    {
        $tenantIdRequest = new Microsoft365TenantIdRequest(
            tenantName: 'test-tenant',
            context: Str::uuid()
        );

        $validator = $this->microsoftOnlineValidator->getTenantIdRequestValidator($tenantIdRequest);

        self::assertTrue($validator->passes());
    }
}
