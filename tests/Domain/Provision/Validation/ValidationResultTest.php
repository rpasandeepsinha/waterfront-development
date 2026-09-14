<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Validation;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\MessageBag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Domain\Provision\Validation\ValidationResult;

#[CoversClass(ValidationResult::class)]
class ValidationResultTest extends TestCase
{
    #[Test]
    public function addValidationError(): void
    {
        $result = new ValidationResult();

        $ipField = 'ipv4';
        $message = 'Invalid ipv4 address';

        self::assertTrue($result->isValid);
        self::assertEmpty($result->messages);

        $result->addValidationError($ipField, $message);

        self::assertFalse($result->isValid);

        self::assertArrayHasKey($ipField, $result->messages);
        self::assertCount(1, $result->messages[$ipField]);
        self::assertSame($message, $result->messages[$ipField][0]);
    }

    #[Test]
    public function validationMessagesGroupedByFieldKey(): void
    {
        $result = new ValidationResult();

        $sslField = 'ssl';
        $ipField = 'ipv4';

        $sslMessage = 'This field is required';
        $sslMessage2 = 'Invalid CRT or something';
        $ipMessage = 'Invalid ipv4 address';

        self::assertTrue($result->isValid);
        self::assertEmpty($result->messages);

        $result->addValidationError($sslField, $sslMessage);
        $result->addValidationError($sslField, $sslMessage2);
        $result->addValidationError($ipField, $ipMessage);

        self::assertFalse($result->isValid);

        self::assertArrayHasKey($sslField, $result->messages);
        self::assertCount(2, $result->messages[$sslField]);
        self::assertCount(1, $result->messages[$ipField]);
        self::assertSame($sslMessage, $result->messages[$sslField][0]);
        self::assertSame($sslMessage2, $result->messages[$sslField][1]);
        self::assertSame($ipMessage, $result->messages[$ipField][0]);
    }

    #[Test]
    public function fromValidatorResultFailed(): void
    {
        $validationErrors = ['field' => ['first-error', 'second-error']];
        $messageBag = new MessageBag($validationErrors);

        $mockValidator = self::createMock(Validator::class);
        $mockValidator->expects(self::once())->method('errors')->willReturn($messageBag);

        $result = ValidationResult::fromValidator($mockValidator);

        self::assertFalse($result->isValid);
        self::assertSame($validationErrors, $result->messages);
    }

    #[Test]
    public function fromValidatorResultSuccess(): void
    {
        $messageBag = new MessageBag();

        $mockValidator = self::createMock(Validator::class);
        $mockValidator->expects(self::once())->method('errors')->willReturn($messageBag);

        $result = ValidationResult::fromValidator($mockValidator);

        self::assertTrue($result->isValid);
    }
}
