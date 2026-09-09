<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Models\DnsRecords;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\DNS\Entities\DnsRecords\CnameRecord;

#[CoversClass(CnameRecord::class)]
class CnameRecordTest extends TestCase
{
    #[Test]
    public function generalUsage(): void
    {
        $record = new CnameRecord('google.com', 'google.nl', 600, true);

        self::assertSame('CNAME', $record->getType());
        self::assertSame('google.com', $record->getName());
        self::assertSame('google.nl', $record->getContent());
        self::assertSame(600, $record->getTtl());
        self::assertTrue($record->isDisabled());
    }

    #[Test]
    public function trimNameDot(): void
    {
        $record = new CnameRecord('google.com.', 'google.nl', 600);

        self::assertSame('google.com', $record->getName());
    }

    #[Test]
    public function getNamePlusDot(): void
    {
        $record = new CnameRecord('google.com', 'google.nl', 600);

        self::assertSame('google.com.', $record->getNamePlusDot());
    }

    #[Test]
    public function trimContentDot(): void
    {
        $record = new CnameRecord('google.com', 'google.nl.', 600);

        self::assertSame('google.nl', $record->getContent());
    }

    #[Test]
    public function getContentPlusDot(): void
    {
        $record = new CnameRecord('google.com', 'google.nl', 600);

        self::assertSame('google.nl.', $record->getContentPlusDot());
    }

    #[Test]
    public function omitDisabled(): void
    {
        $record = new CnameRecord('google.com', 'google.nl', 600);

        self::assertEquals(false, $record->isDisabled());
    }
}
