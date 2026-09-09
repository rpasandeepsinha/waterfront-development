<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Models\DnsRecords;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\DNS\Entities\DnsRecords\MxRecord;

#[CoversClass(MxRecord::class)]
class MxRecordTest extends TestCase
{
    #[Test]
    public function generalUsage(): void
    {
        $record = new MxRecord('google.com', 'mx.spamservice.nl', 10, 600, true);

        self::assertSame('MX', $record->getType());
        self::assertSame('google.com', $record->getName());
        self::assertSame('mx.spamservice.nl', $record->getContent());
        self::assertSame(10, $record->getPriority());
        self::assertSame(600, $record->getTtl());
        self::assertTrue($record->isDisabled());
    }

    #[Test]
    public function trimNameDot(): void
    {
        $record = new MxRecord('google.com.', 'mx.spamservice.nl', 10, 600);

        self::assertSame('google.com', $record->getName());
    }

    #[Test]
    public function getNamePlusDot(): void
    {
        $record = new MxRecord('google.com', 'mx.spamservice.nl', 10, 600);

        self::assertSame('google.com.', $record->getNamePlusDot());
    }

    #[Test]
    public function trimContentDot(): void
    {
        $record = new MxRecord('google.com', 'mx.spamservice.nl.', 10, 600);

        self::assertSame('mx.spamservice.nl', $record->getContent());
    }

    #[Test]
    public function getContentPlusDot(): void
    {
        $record = new MxRecord('google.com', 'mx.spamservice.nl', 10, 600);

        self::assertSame('mx.spamservice.nl.', $record->getContentPlusDot());
    }

    #[Test]
    public function omitDisabled(): void
    {
        $record = new MxRecord('google.com', 'mx.spamservice.nl', 10, 600);

        self::assertEquals(false, $record->isDisabled());
    }
}
