<?php

declare(strict_types=1);

namespace Tests\Infra\PowerDnsClient\Unit\Services;

use Carbon\CarbonImmutable;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsNotSOARecordException;
use Waterfront\Infra\PowerDnsClient\Services\PowerDnsSoaSerialUpdater;

#[CoversClass(PowerDnsSoaSerialUpdater::class)]
class PowerDnsSoaSerialUpdaterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2023-12-01 12:00:00');
    }

    #[DataProvider('soaRecords')]
    #[Test]
    public function increaseSOASerial(string $soaContent, string $expectedSoaContent): void
    {
        $newSoaContent = PowerDnsSoaSerialUpdater::increaseSoaSerial($soaContent);

        self::assertSame($expectedSoaContent, $newSoaContent);
    }

    #[Test]
    public function invalidSOARecordThrowsException(): void
    {
        self::expectException(PdnsNotSOARecordException::class);
        self::expectExceptionMessageIs('Expected a SOA content but got: invalid SOA content');

        PowerDnsSoaSerialUpdater::increaseSoaSerial('invalid SOA content');
    }

    public static function soaRecords(): Iterator
    {
        yield 'serial ending in 01' => [
            'ns4.sandwaveio.dev. domain-admin.testing.test. 2023120101 3600 600 86400 3600',
            'ns4.sandwaveio.dev. domain-admin.testing.test. 2023120102 3600 600 86400 3600',
        ];
        yield 'serial ending in 39' => [
            'ns4.sandwaveio.dev. domain-admin.testing.test. 2023120139 3600 600 86400 3600',
            'ns4.sandwaveio.dev. domain-admin.testing.test. 2023120140 3600 600 86400 3600',
        ];
        yield 'serial ending in 99' => [
            'ns4.sandwaveio.dev. domain-admin.testing.test. 2023120199 3600 600 86400 3600',
            'ns4.sandwaveio.dev. domain-admin.testing.test. 2023120101 3600 600 86400 3600',
        ];
        yield 'different day' => [
            'ns4.sandwaveio.dev. domain-admin.testing.test. 2023103105 3600 600 86400 3600',
            'ns4.sandwaveio.dev. domain-admin.testing.test. 2023120101 3600 600 86400 3600',
        ];
        yield 'serial only 1' => [
            'ns4.sandwaveio.dev. domain-admin.testing.test. 1 3600 600 86400 3600',
            'ns4.sandwaveio.dev. domain-admin.testing.test. 2023120101 3600 600 86400 3600',
        ];
        yield 'serial only 3' => [
            'ns4.sandwaveio.dev. domain-admin.testing.test. 3 3600 600 86400 3600',
            'ns4.sandwaveio.dev. domain-admin.testing.test. 2023120101 3600 600 86400 3600',
        ];
        yield 'serial only text' => [
            'ns4.sandwaveio.dev. domain-admin.testing.test. texthere 3600 600 86400 3600',
            'ns4.sandwaveio.dev. domain-admin.testing.test. 2023120101 3600 600 86400 3600',
        ];
        yield 'negative serial -1' => [
            'ns4.sandwaveio.dev. domain-admin.testing.test. -1 3600 600 86400 3600',
            'ns4.sandwaveio.dev. domain-admin.testing.test. 2023120101 3600 600 86400 3600',
        ];
        yield 'negative serial -5' => [
            'ns4.sandwaveio.dev. domain-admin.testing.test. -1 3600 600 86400 3600',
            'ns4.sandwaveio.dev. domain-admin.testing.test. 2023120101 3600 600 86400 3600',
        ];
    }
}
