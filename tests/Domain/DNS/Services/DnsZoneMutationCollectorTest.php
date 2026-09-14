<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Services;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Entities\AddedDnsRecord;
use Waterfront\Domain\DNS\Entities\ChangedDnsRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Entities\RemovedDnsRecord;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNoSOARecordException;
use Waterfront\Domain\DNS\Services\DnsZoneMutationCollector;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;
use Waterfront\Infra\PowerDnsClient\Services\PowerDnsSoaSerialUpdater;

#[CoversClass(DnsZoneMutationCollector::class)]
class DnsZoneMutationCollectorTest extends TestCase
{
    private DnsZoneMutationCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collector = new DnsZoneMutationCollector();
    }

    #[Test]
    public function collectsRequiredMutations(): void
    {
        $fqdn = 'sandwaveio.dev.';
        $zone = new DnsZone(new Fqdn($fqdn));

        $zone->addRecord(
            new DefaultRecord(
                'NS',
                $fqdn,
                'ns1.sandwaveio.dev.',
                3600,
                disabled: false,
            ),
        );

        $zone->addRecord(
            new DefaultRecord(
                'NS',
                $fqdn,
                'ns2.sandwaveio.dev.',
                3600,
                disabled: false,
            ),
        );

        $zone->addRecord(new DefaultRecord(
            'SOA',
            $fqdn,
            'ns1.sandwaveio.dev. sandwaveio.dev 2023121101 3600 600 86400 3600',
            3600,
            disabled: false,
        ));

        $mutations = $this->collector->collectRequiredMutationsForNewNameservers(
            $zone,
            [
                new Nameserver('ns3.sandwaveio.dev'),
                new Nameserver('ns4.sandwaveio.dev'),
            ],
        );

        self::assertCount(3, $mutations);

        self::assertInstanceOf(ChangedDnsRecord::class, $mutations[0]);
        self::assertInstanceOf(ChangedDnsRecord::class, $mutations[1]);
        self::assertInstanceOf(ChangedDnsRecord::class, $mutations[2]);

        self::assertSame('NS', $mutations[0]->getDnsRecord()->getType());
        self::assertSame('NS', $mutations[1]->getDnsRecord()->getType());
        self::assertSame('SOA', $mutations[2]->getDnsRecord()->getType());

        self::assertSame('ns3.sandwaveio.dev.', $mutations[0]->getDnsRecord()->getContent());
        self::assertSame('ns4.sandwaveio.dev.', $mutations[1]->getDnsRecord()->getContent());
        self::assertSame(
            PowerDnsSoaSerialUpdater::increaseSoaSerial(
                'ns3.sandwaveio.dev. sandwaveio.dev 2023121101 3600 600 86400 3600',
            ),
            $mutations[2]->getDnsRecord()->getContent(),
        );
    }

    #[Test]
    public function collectsRequiredMutationsWithoutSubdomainNSRecords(): void
    {
        $zone = new DnsZone(new Fqdn('sandwaveio.dev'));

        $zone->addRecord(
            new DefaultRecord(
                'NS',
                'sandwaveio.dev.',
                'ns1.sandwaveio.dev.',
                3600,
                disabled: false,
            ),
        );

        $zone->addRecord(
            new DefaultRecord(
                'NS',
                'sandwaveio.dev.',
                'ns2.sandwaveio.dev.',
                3600,
                disabled: false,
            ),
        );

        $zone->addRecord(
            new DefaultRecord(
                'NS',
                'sandwaveio.dev.',
                'ns3.sandwaveio.dev.',
                3600,
                disabled: false,
            ),
        );

        $zone->addRecord(
            new DefaultRecord(
                'NS',
                'subdomain.sandwaveio.dev.',
                'ns1.different.dev.',
                3600,
                disabled: false,
            ),
        );

        $zone->addRecord(
            new DefaultRecord(
                'NS',
                'subdomain.sandwaveio.dev.',
                'ns2.different.dev.',
                3600,
                disabled: false,
            ),
        );

        $zone->addRecord(
            new DefaultRecord(
                'NS',
                'subdomain.sandwaveio.dev.',
                'ns3.different.dev.',
                3600,
                disabled: false,
            ),
        );

        $zone->addRecord(new DefaultRecord(
            'SOA',
            'sandwaveio.dev.',
            'ns1.sandwaveio.dev. sandwaveio.dev 2023121101 3600 600 86400 3600',
            3600,
            disabled: false,
        ));

        $mutations = $this->collector->collectRequiredMutationsForNewNameservers(
            $zone,
            [
                new Nameserver('ns4.sandwaveio.dev'),
                new Nameserver('ns5.sandwaveio.dev'),
                new Nameserver('ns6.sandwaveio.dev'),
            ],
        );

        /**
         * Expect 4 mutations total.
         *
         * 3 nameserver changes:
         * ns1 -> ns4
         * ns2 -> ns5
         * ns3 -> ns6
         *
         * 1 soa update:
         * ns1.sandwaveio.dev. sandwaveio.dev 2023121101 3600 600 86400 3600
         * to
         * ns5.sandwaveio.dev. sandwaveio.dev 2023121101 3600 600 86400 3600
         *
         * No mutations for the subdomain NS records should be present.
         */
        self::assertCount(4, $mutations);

        self::assertInstanceOf(ChangedDnsRecord::class, $mutations[0]);
        self::assertInstanceOf(ChangedDnsRecord::class, $mutations[1]);
        self::assertInstanceOf(ChangedDnsRecord::class, $mutations[2]);

        self::assertSame('NS', $mutations[0]->getDnsRecord()->getType());
        self::assertSame('NS', $mutations[1]->getDnsRecord()->getType());
        self::assertSame('NS', $mutations[2]->getDnsRecord()->getType());
        self::assertSame('SOA', $mutations[3]->getDnsRecord()->getType());

        self::assertSame('ns4.sandwaveio.dev.', $mutations[0]->getDnsRecord()->getContent());
        self::assertSame('ns5.sandwaveio.dev.', $mutations[1]->getDnsRecord()->getContent());
        self::assertSame('ns6.sandwaveio.dev.', $mutations[2]->getDnsRecord()->getContent());
        self::assertSame(
            PowerDnsSoaSerialUpdater::increaseSoaSerial(
                'ns4.sandwaveio.dev. sandwaveio.dev 2023121101 3600 600 86400 3600',
            ),
            $mutations[3]->getDnsRecord()->getContent(),
        );
    }

    #[Test]
    public function collectsRemoveMutationsWhenTooManyNameserversInPdns(): void
    {
        $fqdn = 'sandwaveio.dev.';
        $zone = new DnsZone(new Fqdn($fqdn));

        $zone->addRecord(
            new DefaultRecord(
                'NS',
                $fqdn,
                'ns1.sandwaveio.dev.',
                3600,
                disabled: false,
            ),
        );

        $zone->addRecord(
            new DefaultRecord(
                'NS',
                $fqdn,
                'ns2.sandwaveio.dev.',
                3600,
                disabled: false,
            ),
        );

        $zone->addRecord(new DefaultRecord(
            'SOA',
            $fqdn,
            'ns1.sandwaveio.dev. sandwaveio.dev 2023121101 3600 600 86400 3600',
            3600,
            disabled: false,
        ));

        $mutations = $this->collector->collectRequiredMutationsForNewNameservers(
            $zone,
            [new Nameserver('ns3.sandwaveio.dev')],
        );

        self::assertCount(3, $mutations);

        self::assertInstanceOf(ChangedDnsRecord::class, $mutations[0]);
        self::assertInstanceOf(RemovedDnsRecord::class, $mutations[1]);
        self::assertInstanceOf(ChangedDnsRecord::class, $mutations[2]);

        self::assertSame('NS', $mutations[0]->getDnsRecord()->getType());
        self::assertSame('NS', $mutations[1]->getDnsRecord()->getType());
        self::assertSame('SOA', $mutations[2]->getDnsRecord()->getType());

        self::assertSame('ns2.sandwaveio.dev.', $mutations[1]->getDnsRecord()->getContent());
        self::assertSame('ns3.sandwaveio.dev.', $mutations[0]->getDnsRecord()->getContent());
        self::assertSame(
            PowerDnsSoaSerialUpdater::increaseSoaSerial(
                'ns3.sandwaveio.dev. sandwaveio.dev 2023121101 3600 600 86400 3600',
            ),
            $mutations[2]->getDnsRecord()->getContent(),
        );
    }

    #[Test]
    public function collectsAddMutationsWhenTooLittleNameserversInPdns(): void
    {
        $fqdn = 'sandwaveio.dev.';
        $zone = new DnsZone(new Fqdn($fqdn));

        $zone->addRecord(
            new DefaultRecord(
                'NS',
                $fqdn,
                'ns1.sandwaveio.dev.',
                3600,
                disabled: false,
            ),
        );

        $zone->addRecord(new DefaultRecord(
            'SOA',
            $fqdn,
            'ns1.sandwaveio.dev. sandwaveio.dev 2023121101 3600 600 86400 3600',
            3600,
            disabled: false,
        ));

        $mutations = $this->collector->collectRequiredMutationsForNewNameservers(
            $zone,
            [new Nameserver('ns3.sandwaveio.dev'), new Nameserver('ns4.sandwaveio.dev')],
        );

        self::assertCount(3, $mutations);

        self::assertInstanceOf(ChangedDnsRecord::class, $mutations[0]);
        self::assertInstanceOf(AddedDnsRecord::class, $mutations[1]);
        self::assertInstanceOf(ChangedDnsRecord::class, $mutations[2]);

        self::assertSame('NS', $mutations[0]->getDnsRecord()->getType());
        self::assertSame('NS', $mutations[1]->getDnsRecord()->getType());
        self::assertSame('SOA', $mutations[2]->getDnsRecord()->getType());

        self::assertSame('ns3.sandwaveio.dev.', $mutations[0]->getDnsRecord()->getContent());
        self::assertSame('ns4.sandwaveio.dev.', $mutations[1]->getDnsRecord()->getContent());
        self::assertSame(
            PowerDnsSoaSerialUpdater::increaseSoaSerial(
                'ns3.sandwaveio.dev. sandwaveio.dev 2023121101 3600 600 86400 3600',
            ),
            $mutations[2]->getDnsRecord()->getContent(),
        );
    }

    #[Test]
    public function throwsExceptionWhenNoNameserversProvided(): void
    {
        self::expectException(LogicException::class);
        self::expectExceptionMessageIs('Provided nameservers is empty');
        $this->collector->collectRequiredMutationsForNewNameservers(
            self::createStub(DnsZone::class),
            [],
        );
    }

    #[Test]
    public function throwsExceptionWhenNoSOARecordFound(): void
    {
        $zone = new DnsZone(new Fqdn('sandwaveio.dev'));

        self::expectException(DnsZoneNoSOARecordException::class);
        self::expectExceptionMessageIs('Failed to find SOA record for zone: sandwaveio.dev');
        $this->collector->collectRequiredMutationsForNewNameservers(
            $zone,
            [new Nameserver('ns3.sandwaveio.dev')],
        );
    }

    #[Test]
    public function throwsExceptionWhenSOARecordInvalidContent(): void
    {
        $zone = new DnsZone(new Fqdn('sandwaveio.dev'));
        $zone->addRecord(
            new DefaultRecord(
                'SOA',
                'test',
                'test',
                3600,
                disabled: false,
            ),
        );

        self::expectException(RuntimeException::class);
        self::expectExceptionMessageIs('Invalid content in SOA record: test');
        $this->collector->collectRequiredMutationsForNewNameservers(
            $zone,
            [new Nameserver('ns3.sandwaveio.dev')],
        );
    }
}
