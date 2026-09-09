<?php

declare(strict_types=1);

namespace Tests\Domain\DNS;

use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\TestCase;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\ChangedDnsRecord;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Entities\DnsZoneDiff;
use Waterfront\Domain\DNS\Entities\RemovedDnsRecord;
use Waterfront\Domain\DNS\Hydrators\DnsRecordHydrator;
use Waterfront\Domain\DNS\Interfaces\DnsZoneFactoryInterface;
use Waterfront\Domain\DNS\Services\DnsLogService;
use Waterfront\Domain\DNS\Services\DnsRecordConverter;
use Waterfront\Domain\DNS\Services\DnsZoneService;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PowerDnsClient\PowerDnsClient;

#[CoversClass(DnsService::class)]
class DnsServiceUnitTest extends TestCase
{
    #[Test]
    public function applyDiffToZone(): void
    {
        $pdns = self::createMock(PowerDnsClient::class);

        $dns = new DnsService(
            powerDnsClient: $pdns,
            dnsZoneFactory: self::createStub(DnsZoneFactoryInterface::class),
            configuration: self::createStub(ConfigurationInterface::class),
            jobDispatcher: self::createStub(Dispatcher::class),
            logger: self::createStub(LoggerInterface::class),
            hydrator: self::createStub(DnsRecordHydrator::class),
            dnsLogService: self::createStub(DnsLogService::class),
            dnsRecordConverter: self::createStub(DnsRecordConverter::class),
            dnsZoneService: self::createStub(DnsZoneService::class),
        );

        $zone = self::createStub(DnsZone::class);

        $mutations = [
            self::createMock(RemovedDnsRecord::class),
            self::createMock(ChangedDnsRecord::class),
        ];

        $diff = new DnsZoneDiff($mutations);

        $mutations[0]->expects(self::once())->method('apply')->with($zone);
        $mutations[1]->expects(self::once())->method('apply')->with($zone);
        $pdns->expects(self::once())->method('changeZone')->with($zone);

        $dns->applyDiffToZone($zone, $diff);
    }
}
