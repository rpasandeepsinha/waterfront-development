<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Models\DnsRecords;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;

#[CoversClass(DefaultRecord::class)]
class DefaultRecordTest extends TestCase
{
    #[Test]
    public function generalUsage(): void
    {
        $record = new DefaultRecord('UNKNOWN', 'google.com', 'unknown data format', 600, true);

        self::assertSame('UNKNOWN', $record->getType());
        self::assertSame('google.com', $record->getName());
        self::assertSame('unknown data format', $record->getContent());
        self::assertSame(600, $record->getTtl());
        self::assertTrue($record->isDisabled());
    }

    #[Test]
    public function trimNameDot(): void
    {
        $record = new DefaultRecord('UNKNOWN', 'google.com.', 'unknown data format', 600);

        self::assertSame('google.com', $record->getName());
    }

    #[Test]
    public function getNamePlusDot(): void
    {
        $record = new DefaultRecord('UNKNOWN', 'google.com', 'unknown data format', 600);

        self::assertSame('google.com.', $record->getNamePlusDot());
    }

    #[Test]
    public function omitDisabled(): void
    {
        $record = new DefaultRecord('UNKNOWN', 'google.com', 'unknown data format', 600);

        self::assertEquals(false, $record->isDisabled());
    }
}
