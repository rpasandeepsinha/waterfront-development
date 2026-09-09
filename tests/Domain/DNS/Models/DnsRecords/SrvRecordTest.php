<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Models\DnsRecords;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\DNS\Entities\DnsRecords\SrvRecord;

#[CoversClass(SrvRecord::class)]
class SrvRecordTest extends TestCase
{
    #[Test]
    public function generalUsage(): void
    {
        $record = new SrvRecord('_sip._tcp.example.com', 'bigbox.example.com', 10, 20, 5000, 600, true);

        self::assertSame('SRV', $record->getType());
        self::assertSame('_sip._tcp.example.com', $record->getName());
        self::assertSame('bigbox.example.com', $record->getContent());
        self::assertSame(10, $record->getPriority());
        self::assertSame(20, $record->getWeight());
        self::assertSame(5000, $record->getPort());
        self::assertSame(600, $record->getTtl());
        self::assertTrue($record->isDisabled());
    }

    #[Test]
    public function trimNameDot(): void
    {
        $record = new SrvRecord('_sip._tcp.example.com.', 'bigbox.example.com', 10, 20, 5000, 600);

        self::assertSame('_sip._tcp.example.com', $record->getName());
    }

    #[Test]
    public function getNamePlusDot(): void
    {
        $record = new SrvRecord('_sip._tcp.example.com', 'bigbox.example.com', 10, 20, 5000, 600);

        self::assertSame('_sip._tcp.example.com.', $record->getNamePlusDot());
    }

    #[Test]
    public function trimContentDot(): void
    {
        $record = new SrvRecord('_sip._tcp.example.com', 'bigbox.example.com.', 10, 20, 5000, 600);

        self::assertSame('bigbox.example.com', $record->getContent());
    }

    #[Test]
    public function getContentPlusDot(): void
    {
        $record = new SrvRecord('_sip._tcp.example.com', 'bigbox.example.com', 10, 20, 5000, 600);

        self::assertSame('bigbox.example.com.', $record->getContentPlusDot());
    }

    #[Test]
    public function omitDisabled(): void
    {
        $record = new SrvRecord('_sip._tcp.example.com', 'bigbox.example.com', 10, 20, 5000, 600);

        self::assertEquals(false, $record->isDisabled());
    }
}
