<?php

declare(strict_types=1);

namespace Tests\Infra\Logging\Formatters;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\Logging\Formatters\InsightsFormatter;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(InsightsFormatter::class)]
class InsightsFormatterTest extends TestCase
{
    #[Test]
    public function formatsWithCorrectAttributesFromContext(): void
    {
        $formatter = new InsightsFormatter();
        $record = new LogRecord(
            datetime: new DateTimeImmutable('2024-09-12T09:10:11+02:00'),
            channel: 'test',
            level: Level::Critical,
            message: 'Something to log',
            context: [
                LoggingContextKeys::CUSTOMER_NUMBER => 10000001,
                LoggingContextKeys::SUBSCRIPTION_ID => 12345,
                LoggingContextKeys::META => [
                    'foo' => 'bar',
                    'test' => [
                        'meta',
                    ],
                ],
            ],
            extra: [
                'file' => '/dir/test.php',
                'line' => 42,
            ]
        );
        $formatted = $formatter->format($record);

        $expectedJson = '
        {
            "datetime": "2024-09-12T09:10:11+02:00",
            "channel": "test",
            "level": 500,
            "level_name": "CRITICAL",
            "message": "Something to log",
            "attributes": {
                "customer.number": 10000001,
                "subscription.id": 12345
            },
            "context": {
                "meta": {
                    "foo": "bar",
                    "test": [
                        "meta"
                    ]
                }
            },
            "extra": {
                "file": "/dir/test.php",
                "line": 42
            }
        }';

        self::assertJsonStringEqualsJsonString($expectedJson, $formatted);
    }
}
