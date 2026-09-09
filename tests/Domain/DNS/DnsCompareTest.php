<?php

declare(strict_types=1);

namespace Tests\Domain\DNS;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Domain\DNS\Entities\DnsCompare;
use Waterfront\Domain\DNS\Entities\DnsRecords\AbstractRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\ARecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\MxRecord;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;

#[CoversClass(DnsCompare::class)]
class DnsCompareTest extends TestCase
{
    #[DataProvider('equalsProvider')]
    #[Test]
    public function equals(bool $expected, DnsRecordInterface $contract1, DnsRecordInterface $contract2): void
    {
        self::assertSame($expected, DnsCompare::equals($contract1, $contract2));
    }

    /**
     * @return array<array<int, bool|AbstractRecord>>
     */
    public static function equalsProvider(): iterable
    {
        yield [
            true,
            new DefaultRecord('SOA', 'test.nl', 'test.nl', 600),
            new DefaultRecord('SOA', 'test.nl', 'test.nl', 600),
        ];
        yield [
            true,
            new MxRecord('test.nl', 'test.nl', 10, 600),
            new MxRecord('test.nl', 'test.nl', 10, 600),
        ];
        yield [
            false,
            new MxRecord('test.nl', 'test.nl', 10, 600),
            new MxRecord('test.nl', 'test.nl', 20, 600),
        ];
        yield [
            false,
            new DefaultRecord('MX', 'test.nl', 'test.nl', 600),
            new MxRecord('test.nl', 'test.nl', 10, 600),
        ];
        yield [
            true,
            new DefaultRecord('A', 'test.nl', 'test.nl', 600),
            new ARecord('test.nl', 'test.nl', 600),
        ];
    }
}
