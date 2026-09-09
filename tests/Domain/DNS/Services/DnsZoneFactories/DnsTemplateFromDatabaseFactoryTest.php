<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Services\DnsZoneFactories;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\DnsTemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Services\DnsZoneFactories\DnsZoneFromDatabaseTemplateFactory;

#[CoversClass(DnsZoneFromDatabaseTemplateFactory::class)]
class DnsTemplateFromDatabaseFactoryTest extends IntegrationTestCase
{
    private DnsZoneFromDatabaseTemplateFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = self::resolve(DnsZoneFromDatabaseTemplateFactory::class);
    }

    #[Test]
    public function replacesNameservers(): void
    {
        $dnsTemplate = new DnsTemplateFactory()->createOne([
            'slug' => 'default',
        ]);

        $recordSet = $dnsTemplate->recordSets()->create([
            'name' => '{domain}',
            'type' => 'NS',
            'ttl' => 3600,
        ]);

        $recordSet->rows()->createMany([
            ['content' => '{ns1}.'],
            ['content' => '{ns2}.'],
            ['content' => '{ns3}.'],
        ]);

        $dnsZone = $this->factory->create(
            domain: 'sandwave.io',
            dnsTemplate: 'default',
            nameservers: [
                new Nameserver('ns-01.sandwave.io'),
                new Nameserver('ns-02.sandwave.io'),
                new Nameserver('ns-03.sandwave.io'),
            ],
        );

        $records = $dnsZone->getRecords();
        self::assertCount(3, $records);
        self::assertSame('ns-01.sandwave.io', $records[0]->getContent());
        self::assertSame('ns-02.sandwave.io', $records[1]->getContent());
        self::assertSame('ns-03.sandwave.io', $records[2]->getContent());
    }

    #[Test]
    public function skipsThirdNameserverTemplate(): void
    {
        $dnsTemplate = new DnsTemplateFactory()->createOne([
            'slug' => 'default',
        ]);

        $recordSet = $dnsTemplate->recordSets()->create([
            'name' => '{domain}',
            'type' => 'NS',
            'ttl' => 3600,
        ]);

        $recordSet->rows()->createMany([
            ['content' => '{ns1}.'],
            ['content' => '{ns2}.'],
            ['content' => '{ns3}.'],
        ]);

        $dnsZone = $this->factory->create(
            domain: 'sandwave.io',
            dnsTemplate: 'default',
            nameservers: [
                new Nameserver('ns-01.sandwave.io'),
                new Nameserver('ns-02.sandwave.io'),
            ],
        );

        $records = $dnsZone->getRecords();
        self::assertCount(2, $records);
        self::assertSame('ns-01.sandwave.io', $records[0]->getContent());
        self::assertSame('ns-02.sandwave.io', $records[1]->getContent());
    }
}
