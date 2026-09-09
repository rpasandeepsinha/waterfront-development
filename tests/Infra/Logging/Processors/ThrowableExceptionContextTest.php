<?php

declare(strict_types=1);

namespace Tests\Infra\Logging\Processors;

use DateTimeImmutable;
use InvalidArgumentException;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Waterfront\Infra\Logging\Processors\ThrowableExceptionContext;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(ThrowableExceptionContext::class)]
class ThrowableExceptionContextTest extends TestCase
{
    #[Test]
    public function mustTransformExceptionObjectToContextValues(): void
    {
        $previousException = new InvalidArgumentException('Test argument Biem!');
        $exception = new RuntimeException('Test run Biem!', 1337, $previousException);

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Critical,
            message: 'Something failed {exception.message}',
            context: [
                LoggingContextKeys::EXCEPTION => $exception,
            ],
            extra: []
        );

        $processor = new ThrowableExceptionContext();
        $record = $processor($record);

        self::assertCount(5, $record->context);
        self::assertSame('Test run Biem!', $record->context['exception.message']);
        self::assertSame('RuntimeException(1337)', $record->context['exception.type']);
        self::assertSame(__FILE__ . ' line ' . $exception->getLine(), $record->context['exception.thrown_at']);
        self::assertIsString($record->context['exception.previous']);
        self::assertStringContainsString('Test argument Biem!', $record->context['exception.previous']);
        self::assertStringContainsString('InvalidArgumentException', $record->context['exception.previous']);
    }
}
