<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Services\DnsZoneService;

#[CoversClass(DnsZoneService::class)]
class DnsZoneServiceTest extends IntegrationTestCase
{
    private DnsZoneService $dnsZoneService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dnsZoneService = self::resolve(DnsZoneService::class);
    }

    #[Test]
    public function getHostingDnsRecords(): void
    {
        $domain = 'test.nl';
        $ipv4 = '127.0.0.1';
        $ipv6 = '::1';

        $dnsZone = $this->dnsZoneService->getHostingDnsRecords($domain, $ipv4, $ipv6);

        $records = $dnsZone->getAddedRows();

        foreach ($records as $record) {
            self::assertStringContainsString($domain, $record->getDnsRecord()->getName());

            if ($record->getDnsRecord()->getType() === 'A') {
                self::assertSame($ipv4, $record->getDnsRecord()->getContent());
            }

            if ($record->getDnsRecord()->getType() === 'AAAA') {
                self::assertSame($ipv6, $record->getDnsRecord()->getContent());
            }
        }
    }

    #[Test]
    public function getExternalHostingDnsRecords(): void
    {
        $domain = 'testextern.nl';
        $ipv4 = '10.10.0.1';
        $ipv6 = '::ffff:a0a:1';
        $ipv4Mail = '127.0.0.1';
        $ipv6Mail = '::1';

        $dnsZone = $this->dnsZoneService->getExternalHostingDnsRecords(
            domain: $domain,
            ipv4: $ipv4,
            ipv6: $ipv6,
            ipv4Mail: $ipv4Mail,
            ipv6Mail: $ipv6Mail
        );

        $records = $dnsZone->getAddedRows();

        foreach ($records as $record) {
            self::assertStringContainsString($domain, $record->getDnsRecord()->getName());

            if ($record->getDnsRecord()->getType() === 'A'
                && (
                    str_starts_with($record->getDnsRecord()->getName(), 'mail.')
                ||  str_starts_with($record->getDnsRecord()->getName(), 'smtp.')
                )
            ) {
                self::assertSame($ipv4Mail, $record->getDnsRecord()->getContent());
                continue;
            }

            if ($record->getDnsRecord()->getType() === 'AAAA'
                && (
                    str_starts_with($record->getDnsRecord()->getName(), 'mail.')
                    ||  str_starts_with($record->getDnsRecord()->getName(), 'smtp.')
                )
            ) {
                self::assertSame($ipv6Mail, $record->getDnsRecord()->getContent());
                continue;
            }

            if ($record->getDnsRecord()->getType() === 'A') {
                self::assertSame($ipv4, $record->getDnsRecord()->getContent());
            }

            if ($record->getDnsRecord()->getType() === 'AAAA') {
                self::assertSame($ipv6, $record->getDnsRecord()->getContent());
            }
        }
    }

    #[Test]
    public function getParkingAddressRecords(): void
    {
        $domain = 'test.nl';

        $records = $this->dnsZoneService->getParkingAddressRecords($domain);

        self::assertNotEmpty($records);

        foreach ($records as $record) {
            self::assertContains($record->getType(), ['A', 'AAAA']);
            self::assertStringContainsString($domain, $record->getName());
        }
    }
}
