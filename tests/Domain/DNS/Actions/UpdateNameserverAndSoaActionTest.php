<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Actions;

use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Actions\UpdateNameserverAndSoaAction;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Entities\AddedDnsRecord;
use Waterfront\Domain\DNS\Entities\ChangedDnsRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Entities\DnsZoneDiff;
use Waterfront\Domain\DNS\Interfaces\DnsZoneFactoryInterface;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;

#[CoversClass(UpdateNameserverAndSoaAction::class)]
class UpdateNameserverAndSoaActionTest extends IntegrationTestCase
{
    /**
     * @param string[] $originalNsRecords
     */
    #[DataProvider('nameserverAndSoaRecords')]
    #[Test]
    public function zoneWithLegacyRecordsWillBeReplaced(
        bool $expectUpdate,
        array $originalNsRecords,
        ?string $originalSoaRecord = null,
    ): void {
        $domainName = 'sandwave.io';
        $nameservers = [
            new Nameserver('ns1.cldin.net'),
            new Nameserver('ns2.cldin.net'),
            new Nameserver('ns3.cldin.net'),
        ];

        $templateZone = new DnsZone(new Fqdn($domainName));
        $templateZone->addRecord(new DefaultRecord('SOA', $domainName, 'ns1.cldin.net', 86400));
        foreach ($nameservers as $nameserver) {
            $templateZone->addRecord(new DefaultRecord('NS', $domainName, $nameserver->hostname, 86400));
        }

        $dnsZone = new DnsZone(new Fqdn($domainName));
        if ($originalSoaRecord !== null) {
            $dnsZone->addRecord(new DefaultRecord('SOA', $domainName, $originalSoaRecord, 86400));
        }

        foreach ($originalNsRecords as $nsRecord) {
            $dnsZone->addRecord(new DefaultRecord('NS', $domainName, $nsRecord, 86400));
        }

        $dnsService = self::createMock(DnsService::class);
        $dnsService->expects(self::once())->method('getDnsZone')->willReturn($dnsZone);

        $dnsService
            ->expects($expectUpdate ? self::once() : self::never())
            ->method('applyDiffToZone')
            ->with($dnsZone, self::callback(function (DnsZoneDiff $diff) use ($domainName, $originalSoaRecord) {
                self::assertCount(4, $diff->getChanges());

                if ($originalSoaRecord === null) {
                    $soaDiff = new AddedDnsRecord(new DefaultRecord('SOA', $domainName, 'ns1.cldin.net', 86400));
                    self::assertContainsEquals($soaDiff, $diff->getAddedRows());
                } else {
                    $soaDiff = new ChangedDnsRecord(
                        new DefaultRecord('SOA', $domainName, $originalSoaRecord, 86400),
                        new DefaultRecord('SOA', $domainName, 'ns1.cldin.net', 86400),
                    );
                    self::assertContainsEquals($soaDiff, $diff->getChangedRows());
                }

                return true;
            }));

        $dnsZoneFactory = self::createMock(DnsZoneFactoryInterface::class);
        $dnsZoneFactory
            ->expects($expectUpdate ? self::once() : self::never())
            ->method('create')
            ->with(
                $domainName,
                self::anything(),
                self::anything(),
                self::anything(),
                self::anything(),
                self::anything(),
                self::anything(),
                $nameservers,
            )
            ->willReturn($templateZone);

        $action = new UpdateNameserverAndSoaAction($dnsService, $dnsZoneFactory);
        $action->updateRecords($domainName, $nameservers);
    }

    public static function nameserverAndSoaRecords(): Iterator
    {
        yield 'Legacy hostname in nameservers, not in SOA-record' => [
            'expectUpdate' => true,
            'originalNsRecords' => [
                'ns1.firstfind.nl',
                'custom.server',
            ],
            'originalSoaRecord' => 'somerandomhost.name',
        ];

        yield 'Legacy hostnames in nameservers, not in SOA-record' => [
            'expectUpdate' => true,
            'originalNsRecords' => [
                'ns1.firstfind.nl',
                'ns2.firstfind.nl',
            ],
            'originalSoaRecord' => 'somerandomhost.name',
        ];

        yield 'Legacy hostname in SOA-record, not in nameservers' => [
            'expectUpdate' => true,
            'originalNsRecords' => [
                'ns1.random.host',
                'ns2.random.host',
            ],
            'originalSoaRecord' => 'ns1.firstfind.nl',
        ];

        yield 'Legacy hostname in SOA-record, and in nameservers' => [
            'expectUpdate' => true,
            'originalNsRecords' => [
                'ns1.firstfind.nl',
                'ns2.random.host',
            ],
            'originalSoaRecord' => 'ns1.firstfind.nl',
        ];

        yield 'Legacy hostname not in SOA-record, and not in nameservers' => [
            'expectUpdate' => false,
            'originalNsRecords' => [
                'ns1.random.host',
                'ns2.random.host',
            ],
            'originalSoaRecord' => 'some.random.host',
        ];

        yield 'Legacy hostname not in nameservers, missing SOA-record' => [
            'expectUpdate' => true,
            'originalNsRecords' => [
                'ns1.random.host',
                'ns2.random.host',
            ],
            'originalSoaRecord' => null,
        ];

        yield 'Legacy hostname not in SOA-record, missing nameservers' => [
            'expectUpdate' => true,
            'originalNsRecords' => [],
            'originalSoaRecord' => 'some.random.host',
        ];
    }
}
