<?php

declare(strict_types=1);

namespace Tests\Domain\MailManagement\Integration;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsRecords\MxRecord;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;
use Waterfront\Domain\MailManagement\Jobs\CleanupMailOnlyDns;

#[CoversClass(CleanupMailOnlyDns::class)]
class CleanupMailOnlyDnsTest extends IntegrationTestCase
{
    public const string TEST_DOMAIN = 'example.com';

    public const string TEST_PRIMARY_HOST = 'mail.example.net';

    public const string TEST_FALLBACK_HOST = 'fallback.example.net';

    public const string TEST_EXTERNAL_HOST = 'mail.google.com';

    private DnsService&MockObject $dnsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dnsService = self::createMock(DnsService::class);

        $this->app->bind(DnsService::class, fn () => $this->dnsService);
    }

    #[Test]
    public function cleanupEmptyZone(): void
    {
        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([]);

        $this->dnsService
            ->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));
        $this->dnsService->expects(self::never())->method('deleteRecordFromObject');

        self::resolve(Dispatcher::class)
            ->dispatchSync(new CleanupMailOnlyDns(
                self::TEST_DOMAIN,
                self::TEST_PRIMARY_HOST,
                self::TEST_FALLBACK_HOST,
            ));
    }

    #[Test]
    public function cleanupFilledZone(): void
    {
        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([
            new MxRecord(self::TEST_DOMAIN, self::TEST_PRIMARY_HOST, 10, 3600),
            new MxRecord(self::TEST_DOMAIN, self::TEST_EXTERNAL_HOST, 15, 3600),
            new MxRecord(self::TEST_DOMAIN, self::TEST_FALLBACK_HOST, 20, 3600),
        ]);

        $this->dnsService
            ->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));
        $this->dnsService->expects(self::exactly(2))->method('deleteRecordFromObject');

        self::resolve(Dispatcher::class)
            ->dispatchSync(new CleanupMailOnlyDns(
                self::TEST_DOMAIN,
                self::TEST_PRIMARY_HOST,
                self::TEST_FALLBACK_HOST,
            ));
    }

    #[Test]
    public function cleanupParitallyFilledZone(): void
    {
        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([
            new MxRecord(self::TEST_DOMAIN, self::TEST_FALLBACK_HOST, 20, 3600),
        ]);

        $this->dnsService
            ->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));
        $this->dnsService->expects(self::once())->method('deleteRecordFromObject');

        self::resolve(Dispatcher::class)
            ->dispatchSync(new CleanupMailOnlyDns(
                self::TEST_DOMAIN,
                self::TEST_PRIMARY_HOST,
                self::TEST_FALLBACK_HOST,
            ));
    }

    #[Test]
    public function cleanupNonexistingZone(): void
    {
        $this->dnsService
            ->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willThrowException(new DnsZoneNotFoundException(''));
        $this->dnsService->expects(self::never())->method('deleteRecordFromObject');

        $this->expectException(DnsZoneNotFoundException::class);

        self::resolve(Dispatcher::class)
            ->dispatchSync(new CleanupMailOnlyDns(
                self::TEST_DOMAIN,
                self::TEST_PRIMARY_HOST,
                self::TEST_FALLBACK_HOST,
            ));
    }
}
