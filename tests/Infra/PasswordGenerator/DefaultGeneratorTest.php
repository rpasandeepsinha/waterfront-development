<?php

declare(strict_types=1);

namespace Tests\Infra\PasswordGenerator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\PasswordGenerator\AbstractGenerator;
use Waterfront\Infra\PasswordGenerator\DefaultGenerator;

#[CoversClass(DefaultGenerator::class)]
class DefaultGeneratorTest extends TestCase
{
    #[Test]
    public function defaultGenerator(): void
    {
        $generator = new DefaultGenerator();
        $password = $generator->generatePassword();

        self::assertSame(AbstractGenerator::MIN_LENGTH, strlen($password));
        self::assertMatchesRegularExpression('/[[:digit:]]/', $password);
        self::assertMatchesRegularExpression('/[[:lower:]]/', $password);
        self::assertMatchesRegularExpression('/[[:upper:]]/', $password);
        self::assertMatchesRegularExpression('/[[:punct:]]/', $password);
    }
}
