<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Entities;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Waterfront\Domain\DNS\Entities\AddedDnsRecord;
use Waterfront\Domain\DNS\Entities\ChangedDnsRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\CnameRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Entities\DnsZoneDiff;
use Waterfront\Domain\DNS\Entities\RemovedDnsRecord;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;

#[CoversClass(DnsZone::class)]
class DnsZoneTest extends TestCase
{
    #[DataProvider('removeRecordProvider')]
    #[Test]
    public function removeRecord(DnsZone $expected, DnsZone $input, DnsRecordInterface $toRemove): void
    {
        self::assertSame($input, $input->removeRecord($toRemove));
        self::assertEquals($expected, $input);
    }

    /**
     * @return array<array<int, DnsZone|CnameRecord>>
     */
    public static function removeRecordProvider(): iterable
    {
        $expected = new DnsZone(new Fqdn('remove-me.nl'));
        $input = new DnsZone(new Fqdn('remove-me.nl'));
        $toRemove = new CnameRecord('remove-me.nl', 'google.nl', 600, false);
        yield [
            $expected,
            $input,
            $toRemove,
        ];

        $expected = clone $expected;
        $input = clone $input;
        $input->addRecord($toRemove);
        yield [
            $expected,
            $input,
            clone $toRemove,
        ];
        $expected = clone $input;
        $input = clone $input;
        $toRemove = new CnameRecord('remove-me.nl', 'google.nl', 1200, false);
        yield [
            $expected,
            $input,
            $toRemove,
        ];
    }

    #[DataProvider('diffProvider')]
    #[Test]
    public function diff(DnsZoneDiff $expected, DnsZone $input, DnsZone $other): void
    {
        self::assertEquals($expected, $input->diff($other));
    }

    #[Test]
    public function diffFailsOnDifferentFqdn(): void
    {
        $zone = new DnsZone(new Fqdn('homer-simps.on'));
        $this->expectException(RuntimeException::class);
        $zone->diff(new DnsZone(new Fqdn('marge-simps.on')));
    }

    /**
     * @return array<array<int, DnsZone|DnsZoneDiff>>
     */
    public static function diffProvider(): iterable
    {
        $zone1 = new DnsZone(new Fqdn('test-domain.nl'));
        $zone2 = new DnsZone(new Fqdn('test-domain.nl'));
        yield [
            new DnsZoneDiff([]),
            $zone1,
            $zone2,
        ];

        $zone1 = new DnsZone(new Fqdn('test-domain.nl'));
        $zone2 = new DnsZone(new Fqdn('test-domain.nl'));
        $cname1 = new CnameRecord('test-domain.nl', 'google.nl', 600, true);
        $zone2->addRecord($cname1);

        yield [
            new DnsZoneDiff([new AddedDnsRecord($cname1)]),
            $zone1,
            $zone2,
        ];

        $zone1 = new DnsZone(new Fqdn('test-domain.nl'));
        $zone2 = new DnsZone(new Fqdn('test-domain.nl'));
        $cnameOld = new CnameRecord('test-domain.nl', 'google.nl', 1200, false);
        $cnameNew = new CnameRecord('test-domain.nl', 'google.nl', 1200, true);
        $zone1->addRecord($cnameOld);
        $zone2->addRecord($cnameNew);

        yield [
            new DnsZoneDiff([new ChangedDnsRecord($cnameOld, $cnameNew)]),
            $zone1,
            $zone2,
        ];

        $zone1 = new DnsZone(new Fqdn('test-domain.nl'));
        $zone2 = new DnsZone(new Fqdn('test-domain.nl'));
        $cnameOld = new CnameRecord('test-domain.nl', 'google.nl', 1200, false);
        $zone1->addRecord($cnameOld);

        yield [
            new DnsZoneDiff([new RemovedDnsRecord($cnameOld)]),
            $zone1,
            $zone2,
        ];
    }

    #[Test]
    public function applyChange(): void
    {
        $zone = new DnsZone(new Fqdn('test-domain.nl'));
        $record1 = new CnameRecord('test-domain.nl', 'google.nl', 1200, false);
        $zone->addRecord($record1);
        $record2 = new CnameRecord('test-domain.nl', 'google.nl', 12000, false);
        $zone->applyChange(new ChangedDnsRecord($record1, $record2));
        self::assertEquals([$record2], $zone->getRecords());
    }

    #[Test]
    public function getRecordsOfType(): void
    {
        $zone = new DnsZone(new Fqdn('test-domain.nl'));
        $zone->addRecord(
            new DefaultRecord(
                'NS',
                'test',
                'ns1.sandwaveio.dev.',
                3600,
                disabled: false,
            ),
        );

        $zone->addRecord(
            new DefaultRecord(
                'NS',
                'test',
                'ns2.sandwaveio.dev.',
                3600,
                disabled: false,
            ),
        );

        $zone->addRecord(
            new DefaultRecord(
                'SOA',
                'test',
                'ns1.sandwaveio.dev sandwaveio.dev 1970101000000',
                3600,
                disabled: false,
            ),
        );

        $records = $zone->getRecordsOfType('NS');

        self::assertCount(2, $records);

        self::assertSame('NS', $records[0]->getType());
        self::assertSame('NS', $records[1]->getType());
    }

    #[Test]
    public function getRecordOfType(): void
    {
        $zone = new DnsZone(new Fqdn('test-domain.nl'));
        $zone->addRecord(
            new DefaultRecord(
                'NS',
                'test',
                'ns1.sandwaveio.dev.',
                3600,
                disabled: false,
            ),
        );

        $record = $zone->getRecordOfType('NS');

        self::assertNotNull($record);
        self::assertSame('NS', $record->getType());
    }

    #[Test]
    public function getRecordOfTypeAndName(): void
    {
        $expectedRecord = new DefaultRecord(
            type: 'NS',
            name: 'test',
            content: 'ns1.sandwaveio.dev.',
            ttl: 3600,
            disabled: false,
        );

        $zone = new DnsZone(new Fqdn('test-domain.nl'));

        $zone->addRecord($expectedRecord);
        $zone->addRecord(
            new DefaultRecord(
                type: 'NS',
                name: 'other-test-not-in-result',
                content: 'ns1.other.dev.',
                ttl: 3600,
                disabled: false,
            ),
        );

        $records = $zone->getRecordsOfTypeAndName('NS', 'test');

        self::assertCount(1, $records);
        self::assertSame($expectedRecord, $records[0]);
    }

    #[Test]
    public function getRecordOfTypeNotExist(): void
    {
        $zone = new DnsZone(new Fqdn('test-domain.nl'));

        $record = $zone->getRecordOfType('NS');

        self::assertNull($record);
    }
}
